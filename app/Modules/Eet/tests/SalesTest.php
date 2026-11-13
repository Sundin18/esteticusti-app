<?php

namespace App\Modules\Eet\tests;

use App\Models\Customer;
use App\Models\User;
use App\Modules\Eet\Enums\PaymentMethod;
use App\Modules\Eet\Exports\SalesExport;
use App\Modules\Eet\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class SalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_login_page(): void
    {
        $this->get(route('eet.sales.index'))->assertRedirect(route('login'));
    }

    public function test_signed_in_user_sees_the_sales_with_who_entered_them(): void
    {
        $user = User::factory()->create(['firstname' => 'Jana', 'lastname' => 'Nováková']);
        Sale::factory()->confirmed()->for($user)
            ->for(Customer::factory()->state(['firstname' => 'Petra', 'lastname' => 'Malá']))
            ->create(['receipt_number' => '42', 'total_amount' => '1500.50', 'payment_method' => PaymentMethod::Card]);
        $this->actingAs($user);

        $this->get('/eet/sales')
            ->assertOk()
            ->assertSeeText('42')
            ->assertSeeText("1\u{00A0}500,50\u{00A0}Kč")
            ->assertSeeText('Potvrzena')
            ->assertSeeText('Kartou')
            ->assertSeeText('Petra Malá')
            ->assertSeeText('Jana Nováková');
    }

    public function test_newest_sales_come_first_by_default(): void
    {
        Sale::factory()->create(['receipt_number' => 'STARSI', 'sold_at' => now()->subDay()]);
        Sale::factory()->create(['receipt_number' => 'NOVEJSI', 'sold_at' => now()]);

        Livewire::actingAs(User::factory()->create())
            ->test('eet::sales')
            ->assertSeeInOrder(['NOVEJSI', 'STARSI']);
    }

    public function test_sales_can_be_sorted_by_amount_in_both_directions(): void
    {
        Sale::factory()->create(['receipt_number' => 'LEVNA', 'total_amount' => '100.00']);
        Sale::factory()->create(['receipt_number' => 'DRAHA', 'total_amount' => '900.00']);

        $page = Livewire::actingAs(User::factory()->create())->test('eet::sales');

        $page->call('sort', 'total_amount');
        $page->assertSeeInOrder(['LEVNA', 'DRAHA']);

        $page->call('sort', 'total_amount');
        $page->assertSeeInOrder(['DRAHA', 'LEVNA']);
    }

    public function test_sorting_by_an_unknown_column_is_ignored(): void
    {
        Sale::factory()->create();

        Livewire::actingAs(User::factory()->create())
            ->test('eet::sales')
            ->call('sort', 'pok; drop table eet_sales')
            ->assertSet('sortBy', 'sold_at')
            ->assertOk();
    }

    public function test_sales_can_be_filtered_by_state(): void
    {
        Sale::factory()->confirmed()->create(['receipt_number' => 'POTVRZENA-1']);
        Sale::factory()->awaitingRetry()->create(['receipt_number' => 'OPAKOVAT-1']);

        Livewire::actingAs(User::factory()->create())
            ->test('eet::sales')
            ->set('status', 'awaiting_retry')
            ->assertSee('OPAKOVAT-1')
            ->assertDontSee('POTVRZENA-1');
    }

    public function test_unconfirmed_filter_shows_every_sale_without_a_confirmation(): void
    {
        Sale::factory()->confirmed()->create(['receipt_number' => 'CONFIRMED-1']);
        Sale::factory()->awaitingRetry()->create(['receipt_number' => 'RETRY-1']);
        Sale::factory()->rejected()->create(['receipt_number' => 'REJECTED-1']);
        $this->actingAs(User::factory()->create());

        $this->get(route('eet.sales.index', ['status' => Sale::FILTER_UNCONFIRMED]))
            ->assertOk()
            ->assertSeeText('RETRY-1')
            ->assertSeeText('REJECTED-1')
            ->assertDontSeeText('CONFIRMED-1');
    }

    public function test_cancellation_is_marked_in_the_list(): void
    {
        $sale = Sale::factory()->confirmed()->create(['total_amount' => '250.00']);
        Sale::factory()->cancelling($sale)->create();

        Livewire::actingAs(User::factory()->create())
            ->test('eet::sales')
            ->assertSee('Storno')
            ->assertSee("-250,00\u{00A0}Kč");
    }

    public function test_export_downloads_the_sales_matching_the_current_filter(): void
    {
        Excel::fake();
        $this->travelTo('2027-01-15 10:00:00');

        Livewire::actingAs(User::factory()->create())
            ->test('eet::sales')
            ->set('status', 'confirmed')
            ->set('dateFrom', '2027-01-01')
            ->set('dateTo', '2027-01-31')
            ->call('export');

        Excel::assertDownloaded('trzby-2027-01-15.xlsx', fn (SalesExport $export): bool => $export->status === 'confirmed'
            && $export->dateFrom === '2027-01-01'
            && $export->dateTo === '2027-01-31');
    }

    public function test_sales_can_be_filtered_by_date_range_including_both_days(): void
    {
        Sale::factory()->create(['receipt_number' => 'DEN-PRED', 'sold_at' => '2027-01-09 23:59:59']);
        Sale::factory()->create(['receipt_number' => 'PRVNI-DEN', 'sold_at' => '2027-01-10 00:00:00']);
        Sale::factory()->create(['receipt_number' => 'POSLEDNI-DEN', 'sold_at' => '2027-01-11 23:59:59']);
        Sale::factory()->create(['receipt_number' => 'DEN-POTE', 'sold_at' => '2027-01-12 00:00:00']);

        Livewire::actingAs(User::factory()->create())
            ->test('eet::sales')
            ->set('dateFrom', '2027-01-10')
            ->set('dateTo', '2027-01-11')
            ->assertSee('PRVNI-DEN')
            ->assertSee('POSLEDNI-DEN')
            ->assertDontSee('DEN-PRED')
            ->assertDontSee('DEN-POTE');
    }
}
