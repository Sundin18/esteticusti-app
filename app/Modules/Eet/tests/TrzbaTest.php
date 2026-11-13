<?php

namespace App\Modules\Eet\tests;

use App\Models\User;
use App\Modules\Eet\Enums\StavTrzby;
use App\Modules\Eet\Models\Trzba;
use App\Modules\Eet\Models\Zprava;
use App\Modules\Eet\Services\EetChyba;
use App\Modules\Eet\Services\EetClient;
use App\Modules\Eet\Services\FakeEetClient;
use App\Modules\Eet\Services\NeuplneNastaveni;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TrzbaTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_login_page(): void
    {
        $this->get(route('eet.trzby.detail', Trzba::factory()->create()))->assertRedirect(route('login'));
    }

    public function test_unknown_sale_is_not_found(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/eet/trzby/999')->assertNotFound();
    }

    public function test_detail_shows_the_sale_and_the_history_of_attempts(): void
    {
        $trzba = Trzba::factory()->potvrzena()->create(['porad_cis' => '42', 'celk_trzba' => '1500.50']);
        Zprava::factory()->for($trzba)->sChybou(EetChyba::KOD_DOCASNA, 'Spojení s EET selhalo')->create();
        Zprava::factory()->for($trzba)->create([
            'prvni_zaslani' => false,
            'uuid_zpravy' => 'b3a09b52-7c87-4014-a496-4c7a53cf9125',
            'varovani' => [4 => 'Datum trzby je v budoucnosti'],
        ]);
        $this->actingAs(User::factory()->create());

        $this->get(route('eet.trzby.detail', $trzba))
            ->assertOk()
            ->assertSeeText('Tržba č. 42')
            ->assertSeeText("1\u{00A0}500,50\u{00A0}Kč")
            ->assertSeeText($trzba->pok)
            ->assertSeeInOrder(['Chyba -1', 'Spojení s EET selhalo', 'opakované', 'Přijato', 'Varování 4: Datum trzby je v budoucnosti'])
            ->assertSeeText('b3a09b52-7c87-4014-a496-4c7a53cf9125')
            ->assertDontSee('data-test="odeslat-znovu"', false);
    }

    public function test_unconfirmed_sale_can_be_sent_again_and_gets_confirmed(): void
    {
        $client = $this->fakeClient();
        $trzba = Trzba::factory()->zamitnuta()->create();
        Zprava::factory()->for($trzba)->sChybou(6, 'Neplatny EIC')->create();

        Livewire::actingAs(User::factory()->create())
            ->test('eet::trzba', ['trzba' => $trzba])
            ->assertSeeHtml('data-test="odeslat-znovu"')
            ->call('odesliZnovu')
            ->assertSee('Přijato')
            ->assertDontSeeHtml('data-test="odeslat-znovu"');

        $this->assertSame(StavTrzby::Potvrzena, $trzba->refresh()->stav);
        $this->assertFalse($client->odeslane[0]['prvniZaslani']);
    }

    public function test_failed_resend_shows_the_new_attempt(): void
    {
        $this->fakeClient()->odpovi(new EetChyba('Neplatny podpis zpravy', 4));
        $trzba = Trzba::factory()->kOpakovani()->create();

        Livewire::actingAs(User::factory()->create())
            ->test('eet::trzba', ['trzba' => $trzba])
            ->call('odesliZnovu')
            ->assertSee('Chyba 4')
            ->assertSee('Neplatny podpis zpravy');

        $this->assertSame(StavTrzby::Zamitnuta, $trzba->refresh()->stav);
    }

    public function test_resend_without_a_certificate_changes_nothing(): void
    {
        $this->fakeClient()->odpovi(NeuplneNastaveni::chybiCertifikat());
        $trzba = Trzba::factory()->kOpakovani()->create();

        Livewire::actingAs(User::factory()->create())
            ->test('eet::trzba', ['trzba' => $trzba])
            ->call('odesliZnovu')
            ->assertOk();

        $this->assertSame(StavTrzby::KOpakovani, $trzba->refresh()->stav);
        $this->assertDatabaseCount('eet_zpravy', 0);
    }

    private function fakeClient(): FakeEetClient
    {
        $client = new FakeEetClient;
        $this->app->instance(EetClient::class, $client);

        return $client;
    }
}
