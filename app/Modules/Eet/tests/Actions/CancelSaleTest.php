<?php

namespace App\Modules\Eet\tests\Actions;

use App\Models\Customer;
use App\Models\User;
use App\Modules\Eet\Actions\CancelSale;
use App\Modules\Eet\Actions\SaleCannotBeCancelled;
use App\Modules\Eet\Enums\PaymentMethod;
use App\Modules\Eet\Enums\SaleStatus;
use App\Modules\Eet\Models\Certificate;
use App\Modules\Eet\Models\Sale;
use App\Modules\Eet\Models\Settings;
use App\Modules\Eet\Services\EetClient;
use App\Modules\Eet\Services\FakeEetClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CancelSaleTest extends TestCase
{
    use RefreshDatabase;

    private FakeEetClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->client = new FakeEetClient;
        $this->app->instance(EetClient::class, $this->client);

        Settings::factory()->create()->forceFill(['last_receipt_number' => 99])->save();
        Certificate::factory()->create();
    }

    public function test_full_cancellation_registers_a_negative_sale_linked_to_the_original(): void
    {
        $this->travelTo('2027-09-10 17:08:33');
        $customer = Customer::factory()->create();
        $user = User::factory()->create();
        $sale = Sale::factory()->confirmed()->for($customer)->create([
            'receipt_number' => '42',
            'total_amount' => '5450.00',
            'payment_method' => PaymentMethod::Card,
            'sold_at' => '2027-09-03 16:54:28',
        ]);

        $cancellation = $this->cancel($user, $sale);

        $this->assertSame('-5450.00', $cancellation->total_amount);
        $this->assertSame('100', $cancellation->receipt_number);
        $this->assertTrue($cancellation->cancelledSale?->is($sale));
        $this->assertTrue($cancellation->isCancellation());
        $this->assertSame(SaleStatus::Confirmed, $cancellation->status);
        $this->assertSame(PaymentMethod::Card, $cancellation->payment_method);
        $this->assertTrue($cancellation->customer?->is($customer));
        $this->assertTrue($cancellation->user->is($user));
        $this->assertTrue($cancellation->sold_at->equalTo(now()));

        $this->assertSame('-5450.00', $this->client->sent[0]['sale']->totalAmount);
        $this->assertSame('100', $this->client->sent[0]['sale']->receiptNumber);
        $this->assertTrue($this->client->sent[0]['firstAttempt']);
        $this->assertSame('0.00', $sale->refundableAmount());
    }

    public function test_partial_cancellations_add_up_to_the_original_amount_at_most(): void
    {
        $sale = Sale::factory()->confirmed()->create(['total_amount' => '108.00']);
        $user = User::factory()->create();

        $first = $this->cancel($user, $sale, '30.50');

        $this->assertSame('-30.50', $first->total_amount);
        $this->assertSame('77.50', $sale->refundableAmount());

        try {
            $this->cancel($user, $sale, '77.51');
            $this->fail('A cancellation above the remaining amount should have thrown.');
        } catch (SaleCannotBeCancelled $exception) {
            $this->assertStringContainsString("77,50\u{00A0}Kč", $exception->getMessage());
        }

        $second = $this->cancel($user, $sale, '77.50');

        $this->assertSame('-77.50', $second->total_amount);
        $this->assertSame('0.00', $sale->refundableAmount());
        $this->assertCount(2, $sale->cancellations);
    }

    public function test_fully_cancelled_sale_cannot_be_cancelled_again(): void
    {
        $sale = Sale::factory()->confirmed()->create(['total_amount' => '100.00']);
        Sale::factory()->cancelling($sale)->create();

        $this->expectExceptionObject(new SaleCannotBeCancelled('Tržba už byla celá stornována.'));

        $this->cancel(User::factory()->create(), $sale);
    }

    public function test_rejected_cancellation_does_not_use_up_the_amount(): void
    {
        $sale = Sale::factory()->confirmed()->create(['total_amount' => '100.00']);
        Sale::factory()->cancelling($sale)->rejected()->create();

        $cancellation = $this->cancel(User::factory()->create(), $sale);

        $this->assertSame('-100.00', $cancellation->total_amount);
    }

    public function test_cancellation_cannot_be_cancelled(): void
    {
        $sale = Sale::factory()->confirmed()->create();
        $cancellation = Sale::factory()->cancelling($sale)->create();

        $this->expectExceptionObject(new SaleCannotBeCancelled('Storno nelze stornovat.'));

        $this->cancel(User::factory()->create(), $cancellation);
    }

    public function test_unconfirmed_sale_cannot_be_cancelled(): void
    {
        $sale = Sale::factory()->awaitingRetry()->create();

        try {
            $this->cancel(User::factory()->create(), $sale);
            $this->fail('Cancelling an unconfirmed sale should have thrown.');
        } catch (SaleCannotBeCancelled) {
            $this->assertSame([], $this->client->sent);
            $this->assertDatabaseCount('eet_sales', 1);
        }
    }

    public function test_zero_or_negative_amount_is_refused(): void
    {
        $sale = Sale::factory()->confirmed()->create(['total_amount' => '100.00']);

        foreach (['0', '-10', 'deset'] as $amount) {
            try {
                $this->cancel(User::factory()->create(), $sale, $amount);
                $this->fail("Amount [{$amount}] should have been refused.");
            } catch (SaleCannotBeCancelled) {
                $this->assertDatabaseCount('eet_sales', 1);
            }
        }
    }

    private function cancel(User $user, Sale $sale, ?string $amount = null): Sale
    {
        return $this->app->make(CancelSale::class)->handle($user, $sale, $amount);
    }
}
