<?php

namespace App\Modules\Eet\tests;

use App\Models\User;
use App\Modules\Eet\Models\Trzba;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TrzbyTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_login_page(): void
    {
        $this->get(route('eet.trzby.seznam'))->assertRedirect(route('login'));
    }

    public function test_signed_in_user_sees_the_sales_with_who_entered_them(): void
    {
        $user = User::factory()->create(['firstname' => 'Jana', 'lastname' => 'Nováková']);
        Trzba::factory()->potvrzena()->for($user)->create(['porad_cis' => '42', 'celk_trzba' => '1500.50']);
        $this->actingAs($user);

        $this->get('/eet/trzby')
            ->assertOk()
            ->assertSeeText('42')
            ->assertSeeText("1\u{00A0}500,50\u{00A0}Kč")
            ->assertSeeText('Potvrzena')
            ->assertSeeText('Jana Nováková');
    }

    public function test_newest_sales_come_first_by_default(): void
    {
        Trzba::factory()->create(['porad_cis' => 'STARSI', 'dat_trzby' => now()->subDay()]);
        Trzba::factory()->create(['porad_cis' => 'NOVEJSI', 'dat_trzby' => now()]);

        Livewire::actingAs(User::factory()->create())
            ->test('eet::trzby')
            ->assertSeeInOrder(['NOVEJSI', 'STARSI']);
    }

    public function test_sales_can_be_sorted_by_amount_in_both_directions(): void
    {
        Trzba::factory()->create(['porad_cis' => 'LEVNA', 'celk_trzba' => '100.00']);
        Trzba::factory()->create(['porad_cis' => 'DRAHA', 'celk_trzba' => '900.00']);

        $stranka = Livewire::actingAs(User::factory()->create())->test('eet::trzby');

        $stranka->call('sort', 'celk_trzba');
        $stranka->assertSeeInOrder(['LEVNA', 'DRAHA']);

        $stranka->call('sort', 'celk_trzba');
        $stranka->assertSeeInOrder(['DRAHA', 'LEVNA']);
    }

    public function test_sorting_by_an_unknown_column_is_ignored(): void
    {
        Trzba::factory()->create();

        Livewire::actingAs(User::factory()->create())
            ->test('eet::trzby')
            ->call('sort', 'pok; drop table eet_trzby')
            ->assertSet('sortBy', 'dat_trzby')
            ->assertOk();
    }

    public function test_sales_can_be_filtered_by_state(): void
    {
        Trzba::factory()->potvrzena()->create(['porad_cis' => 'POTVRZENA-1']);
        Trzba::factory()->kOpakovani()->create(['porad_cis' => 'OPAKOVAT-1']);

        Livewire::actingAs(User::factory()->create())
            ->test('eet::trzby')
            ->set('stav', 'k_opakovani')
            ->assertSee('OPAKOVAT-1')
            ->assertDontSee('POTVRZENA-1');
    }

    public function test_sales_can_be_filtered_by_date_range_including_both_days(): void
    {
        Trzba::factory()->create(['porad_cis' => 'DEN-PRED', 'dat_trzby' => '2027-01-09 23:59:59']);
        Trzba::factory()->create(['porad_cis' => 'PRVNI-DEN', 'dat_trzby' => '2027-01-10 00:00:00']);
        Trzba::factory()->create(['porad_cis' => 'POSLEDNI-DEN', 'dat_trzby' => '2027-01-11 23:59:59']);
        Trzba::factory()->create(['porad_cis' => 'DEN-POTE', 'dat_trzby' => '2027-01-12 00:00:00']);

        Livewire::actingAs(User::factory()->create())
            ->test('eet::trzby')
            ->set('od', '2027-01-10')
            ->set('do', '2027-01-11')
            ->assertSee('PRVNI-DEN')
            ->assertSee('POSLEDNI-DEN')
            ->assertDontSee('DEN-PRED')
            ->assertDontSee('DEN-POTE');
    }
}
