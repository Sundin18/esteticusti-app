<?php

namespace App\Modules\Eet\tests;

use App\Models\User;
use App\Modules\Eet\Models\Certificate;
use App\Modules\Eet\Models\Message;
use App\Modules\Eet\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_login_page(): void
    {
        $this->get(route('eet.overview'))->assertRedirect(route('login'));
    }

    public function test_unverified_user_is_redirected_to_the_verification_notice(): void
    {
        $this->actingAs(User::factory()->unverified()->create());

        $this->get(route('eet.overview'))->assertRedirect(route('verification.notice'));
    }

    public function test_signed_in_user_sees_the_overview(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/eet')->assertOk()->assertSeeText('Evidence tržeb');
    }

    public function test_overview_sums_todays_sales_without_rejected_ones(): void
    {
        Sale::factory()->confirmed()->create(['total_amount' => '1000.00']);
        Sale::factory()->awaitingRetry()->create(['total_amount' => '500.50']);
        Sale::factory()->rejected()->create(['total_amount' => '99.00']);
        Sale::factory()->confirmed()->create(['total_amount' => '700.00', 'sold_at' => now()->subDay()]);

        Livewire::actingAs(User::factory()->create())
            ->test('eet::overview')
            ->assertSee("1\u{00A0}500,50\u{00A0}Kč")
            ->assertSee('Počet: 2')
            ->assertSet('unconfirmedCount', 2);
    }

    public function test_overview_warns_about_sales_unconfirmed_for_more_than_24_hours(): void
    {
        Certificate::factory()->create();
        Sale::factory()->awaitingRetry()->create(['sold_at' => now()->subHours(25)]);
        Sale::factory()->rejected()->create(['sold_at' => now()->subHours(30)]);
        Sale::factory()->awaitingRetry()->create(['sold_at' => now()->subHours(23)]);
        Sale::factory()->confirmed()->create(['sold_at' => now()->subHours(40)]);

        Livewire::actingAs(User::factory()->create())
            ->test('eet::overview')
            ->assertSee('Tržby bez potvrzení déle než 24 hodin: 2')
            ->assertSeeHtml(route('eet.sales.index', ['status' => 'unconfirmed']));
    }

    public function test_overview_has_no_alerts_when_everything_is_in_order(): void
    {
        Certificate::factory()->create();
        Sale::factory()->confirmed()->create(['sold_at' => now()->subHours(40)]);
        Sale::factory()->awaitingRetry()->create(['sold_at' => now()->subHours(23)]);

        Livewire::actingAs(User::factory()->create())
            ->test('eet::overview')
            ->assertDontSeeHtml('data-test="overdue-alert"')
            ->assertDontSeeHtml('data-test="certificate-alert"');
    }

    public function test_overview_warns_30_days_before_the_certificate_expires(): void
    {
        $this->travelTo('2027-04-14 12:00:00');
        Certificate::factory()->create(['valid_until' => '2027-05-14 11:43:39']);

        Livewire::actingAs(User::factory()->create())
            ->test('eet::overview')
            ->assertSee('Pokladní certifikát brzy vyprší')
            ->assertSee('Platí do 14. 5. 2027');
    }

    public function test_overview_shows_whether_the_last_sending_succeeded(): void
    {
        Certificate::factory()->create();
        $page = Livewire::actingAs(User::factory()->create())->test('eet::overview');
        $page->assertSee('Zatím nebylo nic odesláno.');

        Message::factory()->create();
        Message::factory()->withError(-1, 'Spojení s EET selhalo')->create(['sent_at' => '2027-01-09 04:25:28']);

        Livewire::actingAs(User::factory()->create())
            ->test('eet::overview')
            ->assertSee('Poslední odeslání 9. 1. 2027 04:25 selhalo');

        Message::factory()->create();

        Livewire::actingAs(User::factory()->create())
            ->test('eet::overview')
            ->assertSee('V pořádku');
    }

    public function test_overview_reports_a_missing_certificate(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test('eet::overview')
            ->assertSee('Chybí');
    }

    public function test_overview_shows_the_certificate_validity(): void
    {
        $this->travelTo('2027-01-09 12:00:00');
        Certificate::factory()->create(['valid_until' => '2027-05-14 11:43:39']);

        Livewire::actingAs(User::factory()->create())
            ->test('eet::overview')
            ->assertSee('Platný')
            ->assertSee('do 14. 5. 2027');
    }

    public function test_overview_flags_an_expired_certificate(): void
    {
        Certificate::factory()->expired()->create();

        Livewire::actingAs(User::factory()->create())
            ->test('eet::overview')
            ->assertSee('Neplatný');
    }
}
