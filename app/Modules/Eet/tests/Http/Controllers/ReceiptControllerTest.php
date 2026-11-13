<?php

namespace App\Modules\Eet\tests\Http\Controllers;

use App\Models\User;
use App\Modules\Eet\Enums\PaymentMethod;
use App\Modules\Eet\Models\Sale;
use App\Modules\Eet\Models\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_login_page(): void
    {
        $sale = Sale::factory()->confirmed()->create();

        $this->get(route('eet.sales.receipt', $sale))->assertRedirect(route('login'));
    }

    public function test_confirmed_sale_is_rendered_as_a_pdf(): void
    {
        Settings::factory()->create();
        $sale = Sale::factory()->confirmed()->create();
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('eet.sales.receipt', $sale));

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $pdf = $response->getContent();
        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_receipt_shows_the_shop_the_sale_and_the_pok(): void
    {
        $sale = Sale::factory()->confirmed()->create([
            'receipt_number' => '42',
            'premises_name' => 'Estetic Ústí',
            'taxpayer_eic' => 'CZ683555118',
            'total_amount' => '1500.50',
            'payment_method' => PaymentMethod::Card,
            'sold_at' => '2027-01-09 04:25:28',
        ]);

        $this->view('eet::receipt', ['sale' => $sale])
            ->assertSeeText('Estetic Ústí')
            ->assertSeeText('EIČ: CZ683555118')
            ->assertSeeText('42')
            ->assertSeeText('9. 1. 2027 04:25:28')
            ->assertSeeText("1\u{00A0}500,50\u{00A0}Kč")
            ->assertSeeText($sale->pok)
            ->assertSeeText('Kartou')
            ->assertSeeText('TESTOVACÍ PROSTŘEDÍ');
    }

    public function test_receipt_of_an_unconfirmed_sale_says_so_instead_of_showing_a_pok(): void
    {
        $sale = Sale::factory()->awaitingRetry()->create();

        $this->view('eet::receipt', ['sale' => $sale])
            ->assertSeeText('dosud nebyla potvrzena')
            ->assertDontSeeText('POK:');
    }

    public function test_receipt_keeps_the_taxpayer_of_the_sale_after_the_settings_change(): void
    {
        Settings::factory()->create(['premises_name' => 'Nový provozovatel', 'taxpayer_eic' => 'CZ00000019']);
        $sale = Sale::factory()->confirmed()->create(['premises_name' => 'Estetic Ústí', 'taxpayer_eic' => 'CZ683555118']);

        $this->view('eet::receipt', ['sale' => $sale])
            ->assertSeeText('Estetic Ústí')
            ->assertSeeText('EIČ: CZ683555118')
            ->assertDontSeeText('Nový provozovatel');
    }

    public function test_rejected_sale_has_no_receipt(): void
    {
        Settings::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->get(route('eet.sales.receipt', Sale::factory()->rejected()->create()))->assertNotFound();
    }
}
