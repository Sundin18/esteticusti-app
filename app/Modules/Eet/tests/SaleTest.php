<?php

namespace App\Modules\Eet\tests;

use App\Models\Customer;
use App\Models\User;
use App\Modules\Eet\Enums\PaymentMethod;
use App\Modules\Eet\Enums\SaleStatus;
use App\Modules\Eet\Models\Certificate;
use App\Modules\Eet\Models\Message;
use App\Modules\Eet\Models\Sale;
use App\Modules\Eet\Models\Settings;
use App\Modules\Eet\Services\EetClient;
use App\Modules\Eet\Services\EetException;
use App\Modules\Eet\Services\FakeEetClient;
use App\Modules\Eet\Services\IncompleteSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_login_page(): void
    {
        $this->get(route('eet.sales.show', Sale::factory()->create()))->assertRedirect(route('login'));
    }

    public function test_unknown_sale_is_not_found(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/eet/sales/999')->assertNotFound();
    }

    public function test_detail_shows_the_sale_and_the_history_of_attempts(): void
    {
        $sale = Sale::factory()->confirmed()
            ->for(Customer::factory()->state(['firstname' => 'Petra', 'lastname' => 'Malá']))
            ->create(['receipt_number' => '42', 'total_amount' => '1500.50', 'payment_method' => PaymentMethod::Cash]);
        Message::factory()->for($sale)->withError(EetException::CODE_TEMPORARY, 'Spojení s EET selhalo')->create();
        Message::factory()->for($sale)->create([
            'is_first_attempt' => false,
            'message_uuid' => 'b3a09b52-7c87-4014-a496-4c7a53cf9125',
            'warnings' => [4 => 'Datum trzby je v budoucnosti'],
        ]);
        $this->actingAs(User::factory()->create());

        $this->get(route('eet.sales.show', $sale))
            ->assertOk()
            ->assertSeeText('Tržba č. 42')
            ->assertSeeText("1\u{00A0}500,50\u{00A0}Kč")
            ->assertSeeText($sale->pok)
            ->assertSeeText('Hotově')
            ->assertSeeText('Petra Malá')
            ->assertSeeInOrder(['Chyba -1', 'Spojení s EET selhalo', 'opakované', 'Přijato', 'Varování 4: Datum trzby je v budoucnosti'])
            ->assertSeeText('b3a09b52-7c87-4014-a496-4c7a53cf9125')
            ->assertDontSee('data-test="resend"', false);
    }

    public function test_unconfirmed_sale_can_be_sent_again_and_gets_confirmed(): void
    {
        $client = $this->fakeClient();
        $sale = Sale::factory()->rejected()->create();
        Message::factory()->for($sale)->withError(6, 'Neplatny EIC')->create();

        Livewire::actingAs(User::factory()->create())
            ->test('eet::sale', ['sale' => $sale])
            ->assertSeeHtml('data-test="resend"')
            ->call('resend')
            ->assertSee('Přijato')
            ->assertDontSeeHtml('data-test="resend"');

        $this->assertSame(SaleStatus::Confirmed, $sale->refresh()->status);
        $this->assertFalse($client->sent[0]['firstAttempt']);
    }

    public function test_failed_resend_shows_the_new_attempt(): void
    {
        $this->fakeClient()->respondWith(new EetException('Neplatny podpis zpravy', 4));
        $sale = Sale::factory()->awaitingRetry()->create();

        Livewire::actingAs(User::factory()->create())
            ->test('eet::sale', ['sale' => $sale])
            ->call('resend')
            ->assertSee('Chyba 4')
            ->assertSee('Neplatny podpis zpravy');

        $this->assertSame(SaleStatus::Rejected, $sale->refresh()->status);
    }

    public function test_resend_without_a_certificate_changes_nothing(): void
    {
        $this->fakeClient()->respondWith(IncompleteSetup::missingCertificate());
        $sale = Sale::factory()->awaitingRetry()->create();

        Livewire::actingAs(User::factory()->create())
            ->test('eet::sale', ['sale' => $sale])
            ->call('resend')
            ->assertOk();

        $this->assertSame(SaleStatus::AwaitingRetry, $sale->refresh()->status);
        $this->assertDatabaseCount('eet_messages', 0);
    }

    public function test_confirmed_sale_can_be_partially_cancelled_from_the_detail(): void
    {
        $this->fakeClient();
        Settings::factory()->create();
        Certificate::factory()->create();
        $sale = Sale::factory()->confirmed()->create(['total_amount' => '108.00']);

        $page = Livewire::actingAs(User::factory()->create())
            ->test('eet::sale', ['sale' => $sale])
            ->assertSeeHtml('data-test="cancel-sale"')
            ->assertSet('cancellationAmount', '108,00')
            ->set('cancellationAmount', '30,50')
            ->call('cancel')
            ->assertHasNoErrors();

        $cancellation = $sale->cancellations()->sole();

        $this->assertSame('-30.50', $cancellation->total_amount);
        $page->assertRedirect(route('eet.sales.show', $cancellation));
    }

    public function test_cancelling_more_than_remains_is_rejected(): void
    {
        $this->fakeClient();
        Settings::factory()->create();
        Certificate::factory()->create();
        $sale = Sale::factory()->confirmed()->create(['total_amount' => '108.00']);

        Livewire::actingAs(User::factory()->create())
            ->test('eet::sale', ['sale' => $sale])
            ->set('cancellationAmount', '108,01')
            ->call('cancel')
            ->assertHasErrors('cancellationAmount')
            ->assertNoRedirect();

        $this->assertDatabaseCount('eet_sales', 1);
    }

    public function test_cancellation_is_not_offered_for_unconfirmed_sales_or_cancellations(): void
    {
        $sale = Sale::factory()->confirmed()->create();
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('eet::sale', ['sale' => Sale::factory()->cancelling($sale)->create()])
            ->assertDontSeeHtml('data-test="cancel-sale"');

        Livewire::actingAs($user)
            ->test('eet::sale', ['sale' => Sale::factory()->awaitingRetry()->create()])
            ->assertDontSeeHtml('data-test="cancel-sale"');
    }

    public function test_detail_links_a_cancellation_with_its_original_sale(): void
    {
        $sale = Sale::factory()->confirmed()->create(['receipt_number' => '42', 'total_amount' => '100.00']);
        $cancellation = Sale::factory()->cancelling($sale)->create(['receipt_number' => '43']);
        $this->actingAs(User::factory()->create());

        $this->get(route('eet.sales.show', $cancellation))
            ->assertOk()
            ->assertSeeText('Storno č. 43')
            ->assertSee(route('eet.sales.show', $sale));

        $this->get(route('eet.sales.show', $sale))
            ->assertOk()
            ->assertSeeText('Storna této tržby')
            ->assertSee(route('eet.sales.show', $cancellation))
            ->assertSeeText("Zbývá ke stornování: 0,00\u{00A0}Kč")
            ->assertDontSee('data-test="cancel-sale"', false);
    }

    private function fakeClient(): FakeEetClient
    {
        $client = new FakeEetClient;
        $this->app->instance(EetClient::class, $client);

        return $client;
    }
}
