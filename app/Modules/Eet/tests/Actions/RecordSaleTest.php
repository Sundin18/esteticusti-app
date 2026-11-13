<?php

namespace App\Modules\Eet\tests\Actions;

use App\Models\Customer;
use App\Models\User;
use App\Modules\Eet\Actions\RecordSale;
use App\Modules\Eet\Enums\PaymentMethod;
use App\Modules\Eet\Enums\SaleStatus;
use App\Modules\Eet\Jobs\ResendSale;
use App\Modules\Eet\Models\Certificate;
use App\Modules\Eet\Models\Sale;
use App\Modules\Eet\Models\Settings;
use App\Modules\Eet\Services\EetClient;
use App\Modules\Eet\Services\EetException;
use App\Modules\Eet\Services\EetResponse;
use App\Modules\Eet\Services\FakeEetClient;
use App\Modules\Eet\Services\IncompleteSetup;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecordSaleTest extends TestCase
{
    use RefreshDatabase;

    private FakeEetClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->client = new FakeEetClient;
        $this->app->instance(EetClient::class, $this->client);
    }

    public function test_confirmed_sale_stores_the_pok_and_the_attempt(): void
    {
        $this->freezeSecond();
        $this->configureModule();
        $user = User::factory()->create();
        $this->client->respondWith(new EetResponse(
            messageUuid: 'b3a09b52-7c87-4014-a496-4c7a53cf9125',
            pok: '987a6be5-6af5-44f3-b4fc-987654321000-ff',
            isTest: true,
            warnings: [1 => 'EIC se neshoduje s certifikatem'],
            requestXml: '<pozadavek/>',
            responseXml: '<odpoved/>',
        ));

        $customer = Customer::factory()->create();

        $sale = $this->recordSale($user, '1500.50', PaymentMethod::Card, $customer);

        $this->assertSame(SaleStatus::Confirmed, $sale->status);
        $this->assertSame('987a6be5-6af5-44f3-b4fc-987654321000-ff', $sale->pok);
        $this->assertTrue($sale->is_test);
        $this->assertSame('1500.50', $sale->total_amount);
        $this->assertTrue($sale->sold_at->equalTo(now()));
        $this->assertTrue($sale->user->is($user));
        $this->assertSame(PaymentMethod::Card, $sale->payment_method);
        $this->assertTrue($sale->customer?->is($customer));

        $message = $sale->messages()->sole();

        $this->assertSame('b3a09b52-7c87-4014-a496-4c7a53cf9125', $message->message_uuid);
        $this->assertTrue($message->is_first_attempt);
        $this->assertSame('<pozadavek/>', $message->request_xml);
        $this->assertSame('<odpoved/>', $message->response_xml);
        $this->assertSame([1 => 'EIC se neshoduje s certifikatem'], $message->warnings);
        $this->assertTrue($message->isSuccessful());

        $this->assertSame('1500.50', $this->client->sent[0]['sale']->totalAmount);
        $this->assertTrue($this->client->sent[0]['firstAttempt']);
        $this->assertFalse($this->client->sent[0]['verifyOnly']);
        Queue::assertNothingPushed();
    }

    public function test_sale_keeps_the_taxpayer_and_premises_valid_when_it_was_recorded(): void
    {
        $this->configureModule();
        $settings = Settings::query()->sole();
        $settings->update(['taxpayer_eic' => 'CZ683555118', 'premises_name' => 'Estetic Ústí', 'premises_address' => 'Mírové náměstí 1', 'unit_id' => 101, 'register_id' => 'P1']);

        $sale = $this->recordSale(User::factory()->create(), '100.00');
        $settings->update(['taxpayer_eic' => 'CZ00000019', 'premises_name' => 'Nový provozovatel', 'unit_id' => 7, 'register_id' => 'JINA']);

        $sale->refresh();

        $this->assertSame('CZ683555118', $sale->taxpayer_eic);
        $this->assertSame('Estetic Ústí', $sale->premises_name);
        $this->assertSame('Mírové náměstí 1', $sale->premises_address);
        $this->assertSame(101, $sale->unit_id);
        $this->assertSame('P1', $sale->register_id);
        $this->assertSame('CZ683555118', $this->client->sent[0]['sale']->taxpayerEic);
    }

    public function test_sale_without_a_customer_is_recorded(): void
    {
        $this->configureModule();

        $sale = $this->recordSale(User::factory()->create(), '100.00', PaymentMethod::Cash);

        $this->assertSame(PaymentMethod::Cash, $sale->payment_method);
        $this->assertNull($sale->customer_id);
    }

    public function test_deleted_customer_stays_on_their_sales(): void
    {
        $this->configureModule();
        $customer = Customer::factory()->create();
        $sale = $this->recordSale(User::factory()->create(), '100.00', PaymentMethod::Cash, $customer);

        $customer->delete();

        $this->assertSoftDeleted($customer);
        $this->assertTrue($sale->refresh()->customer?->is($customer));
    }

    public function test_sales_get_consecutive_receipt_numbers(): void
    {
        $this->configureModule(['last_receipt_number' => 41]);
        $user = User::factory()->create();

        $first = $this->recordSale($user, '100.00');
        $second = $this->recordSale($user, '200.00');

        $this->assertSame('42', $first->receipt_number);
        $this->assertSame('43', $second->receipt_number);
        $this->assertSame(43, Settings::query()->sole()->last_receipt_number);
    }

    public function test_unavailable_service_keeps_the_sale_and_queues_another_attempt(): void
    {
        $this->freezeSecond();
        $this->configureModule();
        $this->client->respondWith(new EetException('Spojení s EET selhalo', EetException::CODE_TEMPORARY, '<pozadavek/>'));

        $sale = $this->recordSale(User::factory()->create(), '100.00');

        $this->assertSame(SaleStatus::AwaitingRetry, $sale->status);
        $this->assertNull($sale->pok);
        $this->assertSame(EetException::CODE_TEMPORARY, $sale->messages()->sole()->error_code);
        Queue::assertPushed(
            ResendSale::class,
            fn (ResendSale $job): bool => $job->sale->is($sale)
                && $job->delay instanceof DateTimeInterface
                && now()->addMinute()->equalTo($job->delay),
        );
    }

    public function test_rejected_sale_is_not_queued(): void
    {
        $this->configureModule();
        $this->client->respondWith(new EetException('Neplatny EIC', 6, '<pozadavek/>', '<odpoved/>'));

        $sale = $this->recordSale(User::factory()->create(), '100.00');

        $this->assertSame(SaleStatus::Rejected, $sale->status);
        $this->assertSame('Neplatny EIC', $sale->messages()->sole()->error);
        Queue::assertNothingPushed();
    }

    public function test_missing_settings_record_nothing(): void
    {
        Certificate::factory()->create();

        try {
            $this->recordSale(User::factory()->create(), '100.00');
            $this->fail('Recording without settings should have thrown.');
        } catch (IncompleteSetup) {
            $this->assertDatabaseCount('eet_sales', 0);
            $this->assertSame([], $this->client->sent);
        }
    }

    public function test_missing_certificate_records_nothing_and_uses_no_receipt_number(): void
    {
        Settings::factory()->create();

        try {
            $this->recordSale(User::factory()->create(), '100.00');
            $this->fail('Recording without a certificate should have thrown.');
        } catch (IncompleteSetup) {
            $this->assertDatabaseCount('eet_sales', 0);
            $this->assertSame(0, Settings::query()->sole()->last_receipt_number);
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function configureModule(array $settings = []): void
    {
        Settings::factory()->create()->forceFill($settings)->save();
        Certificate::factory()->create();
    }

    private function recordSale(
        User $user,
        string $amount,
        PaymentMethod $paymentMethod = PaymentMethod::Cash,
        ?Customer $customer = null,
    ): Sale {
        return $this->app->make(RecordSale::class)->handle($user, $amount, $paymentMethod, $customer);
    }
}
