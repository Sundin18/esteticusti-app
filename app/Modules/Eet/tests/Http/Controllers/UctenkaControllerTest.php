<?php

namespace App\Modules\Eet\tests\Http\Controllers;

use App\Models\User;
use App\Modules\Eet\Models\Nastaveni;
use App\Modules\Eet\Models\Trzba;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UctenkaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_login_page(): void
    {
        $trzba = Trzba::factory()->potvrzena()->create();

        $this->get(route('eet.trzby.uctenka', $trzba))->assertRedirect(route('login'));
    }

    public function test_confirmed_sale_is_rendered_as_a_pdf(): void
    {
        Nastaveni::factory()->create();
        $trzba = Trzba::factory()->potvrzena()->create();
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('eet.trzby.uctenka', $trzba));

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $pdf = $response->getContent();
        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_receipt_shows_the_shop_the_sale_and_the_pok(): void
    {
        $nastaveni = Nastaveni::factory()->create(['nazev_provozovny' => 'Estetic Ústí', 'eic_popl' => 'CZ683555118']);
        $trzba = Trzba::factory()->potvrzena()->create([
            'porad_cis' => '42',
            'celk_trzba' => '1500.50',
            'dat_trzby' => '2027-01-09 04:25:28',
        ]);

        $this->view('eet::uctenka', ['trzba' => $trzba, 'nastaveni' => $nastaveni])
            ->assertSeeText('Estetic Ústí')
            ->assertSeeText('EIČ: CZ683555118')
            ->assertSeeText('42')
            ->assertSeeText('9. 1. 2027 04:25:28')
            ->assertSeeText("1\u{00A0}500,50\u{00A0}Kč")
            ->assertSeeText($trzba->pok)
            ->assertSeeText('TESTOVACÍ PROSTŘEDÍ');
    }

    public function test_receipt_of_an_unconfirmed_sale_says_so_instead_of_showing_a_pok(): void
    {
        $trzba = Trzba::factory()->kOpakovani()->create();

        $this->view('eet::uctenka', ['trzba' => $trzba, 'nastaveni' => Nastaveni::factory()->create()])
            ->assertSeeText('dosud nebyla potvrzena')
            ->assertDontSeeText('POK:');
    }

    public function test_rejected_sale_has_no_receipt(): void
    {
        Nastaveni::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->get(route('eet.trzby.uctenka', Trzba::factory()->zamitnuta()->create()))->assertNotFound();
    }
}
