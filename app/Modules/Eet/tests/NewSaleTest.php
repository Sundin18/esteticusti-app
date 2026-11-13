<?php

namespace App\Modules\Eet\tests;

use App\Models\Customer;
use App\Models\User;
use App\Modules\Eet\Enums\SaleStatus;
use App\Modules\Eet\Enums\PaymentMethod;
use App\Modules\Eet\Models\Certificate;
use App\Modules\Eet\Models\Settings;
use App\Modules\Eet\Models\Sale;
use App\Modules\Eet\Services\EetException;
use App\Modules\Eet\Services\EetClient;
use App\Modules\Eet\Services\FakeEetClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class NewSaleTest extends TestCase
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

    public function test_guest_is_redirected_to_the_login_page(): void
    {
        $this->get(route('eet.sales.create'))->assertRedirect(route('login'));
    }

    public function test_signed_in_user_sees_the_form(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/eet/sales/new')->assertOk()->assertSeeText('Zaevidovat tržbu');
    }

    public function test_user_records_a_sale_and_sees_its_pok(): void
    {
        $this->configureModule();
        $user = User::factory()->create();
        $customer = Customer::factory()->create();

        $page = Livewire::actingAs($user)
            ->test('eet::new-sale')
            ->set('amount', '1 500,50')
            ->set('paymentMethod', 'card')
            ->set('customerId', $customer->id)
            ->call('record')
            ->assertHasNoErrors()
            ->assertSet('amount', '')
            ->assertSet('paymentMethod', '')
            ->assertSet('customerId', null);

        $sale = Sale::query()->sole();

        $this->assertSame('1500.50', $sale->total_amount);
        $this->assertSame(SaleStatus::Confirmed, $sale->status);
        $this->assertTrue($sale->user->is($user));
        $this->assertSame(PaymentMethod::Card, $sale->payment_method);
        $this->assertTrue($sale->customer?->is($customer));

        $page
            ->assertSee('potvrzena')
            ->assertSee("POK: {$sale->pok}")
            ->assertSeeHtml(route('eet.sales.receipt', $sale));
    }

    public function test_unavailable_service_tells_the_user_the_sale_will_be_sent_later(): void
    {
        $this->configureModule();
        $this->client->respondWith(new EetException('Spojení s EET selhalo', EetException::CODE_TEMPORARY));

        Livewire::actingAs(User::factory()->create())
            ->test('eet::new-sale')
            ->set('amount', '100')
            ->set('paymentMethod', 'cash')
            ->call('record')
            ->assertSee('EET je nedostupná')
            ->assertSee('Tržba se odešle automaticky');
    }

    public function test_rejected_sale_shows_the_reason_and_offers_no_receipt(): void
    {
        $this->configureModule();
        $this->client->respondWith(new EetException('Neplatny EIC', 6));

        Livewire::actingAs(User::factory()->create())
            ->test('eet::new-sale')
            ->set('amount', '100')
            ->set('paymentMethod', 'cash')
            ->call('record')
            ->assertSee('zamítnuta systémem EET')
            ->assertSee('Neplatny EIC')
            ->assertDontSeeHtml('data-test="receipt"');
    }

    #[TestWith([''])]
    #[TestWith(['0'])]
    #[TestWith(['-50'])]
    #[TestWith(['12,345'])]
    #[TestWith(['sto korun'])]
    #[TestWith(['100000000'])]
    public function test_invalid_amount_is_rejected(string $amount): void
    {
        $this->configureModule();

        Livewire::actingAs(User::factory()->create())
            ->test('eet::new-sale')
            ->set('amount', $amount)
            ->set('paymentMethod', 'cash')
            ->call('record')
            ->assertHasErrors('amount');

        $this->assertDatabaseCount('eet_sales', 0);
    }

    public function test_sale_can_be_recorded_without_a_customer(): void
    {
        $this->configureModule();

        Livewire::actingAs(User::factory()->create())
            ->test('eet::new-sale')
            ->set('amount', '100')
            ->set('paymentMethod', 'cash')
            ->call('record')
            ->assertHasNoErrors();

        $this->assertNull(Sale::query()->sole()->customer_id);
    }

    #[TestWith([''])]
    #[TestWith(['prevodem'])]
    public function test_payment_method_must_be_cash_or_card(string $paymentMethod): void
    {
        $this->configureModule();

        Livewire::actingAs(User::factory()->create())
            ->test('eet::new-sale')
            ->set('amount', '100')
            ->set('paymentMethod', $paymentMethod)
            ->call('record')
            ->assertHasErrors('paymentMethod');

        $this->assertDatabaseCount('eet_sales', 0);
    }

    public function test_unknown_customer_is_rejected(): void
    {
        $this->configureModule();

        Livewire::actingAs(User::factory()->create())
            ->test('eet::new-sale')
            ->set('amount', '100')
            ->set('paymentMethod', 'cash')
            ->set('customerId', 999)
            ->call('record')
            ->assertHasErrors('customerId');

        $this->assertDatabaseCount('eet_sales', 0);
    }

    public function test_deleted_customer_is_not_offered_and_cannot_be_chosen(): void
    {
        $this->configureModule();
        $customer = Customer::factory()->create(['firstname' => 'Smazaná', 'lastname' => 'Klientka']);
        $customer->delete();

        Livewire::actingAs(User::factory()->create())
            ->test('eet::new-sale')
            ->assertDontSee('Smazaná Klientka')
            ->set('amount', '100')
            ->set('paymentMethod', 'cash')
            ->set('customerId', $customer->id)
            ->call('record')
            ->assertHasErrors('customerId');

        $this->assertDatabaseCount('eet_sales', 0);
    }

    public function test_form_offers_existing_customers(): void
    {
        Customer::factory()->create(['firstname' => 'Jana', 'lastname' => 'Nováková']);

        Livewire::actingAs(User::factory()->create())
            ->test('eet::new-sale')
            ->assertSee('Jana Nováková');
    }

    public function test_missing_certificate_is_explained_to_the_user(): void
    {
        Settings::factory()->create();

        Livewire::actingAs(User::factory()->create())
            ->test('eet::new-sale')
            ->set('amount', '100')
            ->set('paymentMethod', 'cash')
            ->call('record')
            ->assertHasErrors('amount')
            ->assertSee('Nejprve nahrajte pokladní certifikát.');

        $this->assertDatabaseCount('eet_sales', 0);
    }

    private function configureModule(): void
    {
        Settings::factory()->create();
        Certificate::factory()->create();
    }
}
