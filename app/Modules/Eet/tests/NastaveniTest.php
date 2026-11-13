<?php

namespace App\Modules\Eet\tests;

use App\Core\Authorization\UserRole;
use App\Models\User;
use App\Modules\Eet\database\factories\CertifikatFactory;
use App\Modules\Eet\Enums\Prostredi;
use App\Modules\Eet\Models\Certifikat;
use App\Modules\Eet\Models\Nastaveni;
use App\Modules\Eet\Services\EetChyba;
use App\Modules\Eet\Services\EetClient;
use App\Modules\Eet\Services\FakeEetClient;
use App\Modules\Eet\Services\NeuplneNastaveni;
use App\Modules\Eet\Services\Odpoved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class NastaveniTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_login_page(): void
    {
        $this->get(route('eet.nastaveni'))->assertRedirect(route('login'));
    }

    public function test_settings_are_forbidden_without_the_administrator_role(): void
    {
        $this->actingAs(User::factory()->withRole(UserRole::Recepce)->create());

        $this->get(route('eet.nastaveni'))->assertForbidden();
    }

    public function test_administrator_sees_the_saved_settings(): void
    {
        Nastaveni::factory()->create(['eic_popl' => 'CZ683555118', 'nazev_provozovny' => 'Estetic Ústí']);
        $this->actingAs($this->spravce());

        $this->get('/eet/nastaveni')->assertOk();

        $this->stranka()
            ->assertSet('eic_popl', 'CZ683555118')
            ->assertSet('nazev_provozovny', 'Estetic Ústí')
            ->assertSet('prostredi', 'test');
    }

    public function test_administrator_saves_the_settings(): void
    {
        $this->vyplnena($this->stranka())
            ->call('ulozNastaveni')
            ->assertHasNoErrors();

        $nastaveni = Nastaveni::query()->sole();

        $this->assertSame('CZ683555118', $nastaveni->eic_popl);
        $this->assertSame('Estetic Ústí', $nastaveni->nazev_provozovny);
        $this->assertSame('Masarykova 1, Ústí nad Labem', $nastaveni->adresa_provozovny);
        $this->assertSame(101, $nastaveni->id_jednotky);
        $this->assertSame('P1', $nastaveni->id_pokl);
        $this->assertSame(Prostredi::Produkce, $nastaveni->prostredi);
        $this->assertSame(5, $nastaveni->mezni_doba_odezvy);
    }

    public function test_saving_again_updates_the_single_settings_row_and_keeps_the_receipt_counter(): void
    {
        Nastaveni::factory()->create()->forceFill(['posledni_porad_cis' => 42])->save();

        $this->vyplnena($this->stranka())->call('ulozNastaveni')->assertHasNoErrors();

        $nastaveni = Nastaveni::query()->sole();

        $this->assertSame('CZ683555118', $nastaveni->eic_popl);
        $this->assertSame(42, $nastaveni->posledni_porad_cis);
    }

    public function test_values_outside_the_eet_formats_are_rejected(): void
    {
        $this->vyplnena($this->stranka())
            ->set('eic_popl', '683555118')
            ->set('id_jednotky', '0')
            ->set('id_pokl', 'pokladna č. 1')
            ->set('prostredi', 'vyvoj')
            ->set('mezni_doba_odezvy', 1)
            ->call('ulozNastaveni')
            ->assertHasErrors(['eic_popl', 'id_jednotky', 'id_pokl', 'prostredi', 'mezni_doba_odezvy']);

        $this->assertDatabaseCount('eet_nastaveni', 0);
    }

    public function test_saving_is_forbidden_without_the_administrator_role(): void
    {
        $this->vyplnena($this->stranka(User::factory()->withRole(UserRole::Terapeut)->create()))
            ->call('ulozNastaveni')
            ->assertForbidden();

        $this->assertDatabaseCount('eet_nastaveni', 0);
    }

    public function test_administrator_uploads_a_certificate(): void
    {
        Storage::fake('local');

        $this->stranka()
            ->set('souborCertifikatu', $this->souborP12('tajne-heslo'))
            ->set('hesloCertifikatu', 'tajne-heslo')
            ->call('nahrajCertifikat')
            ->assertHasNoErrors()
            ->assertSet('hesloCertifikatu', '')
            ->assertSee('CZ00000019');

        $this->assertSame('CZ00000019', Certifikat::aktivni()?->predmet);
    }

    public function test_certificate_with_a_wrong_password_is_rejected(): void
    {
        Storage::fake('local');

        $this->stranka()
            ->set('souborCertifikatu', $this->souborP12('tajne-heslo'))
            ->set('hesloCertifikatu', 'spatne-heslo')
            ->call('nahrajCertifikat')
            ->assertHasErrors('souborCertifikatu');

        $this->assertDatabaseCount('eet_certifikaty', 0);
    }

    public function test_uploading_is_forbidden_without_the_administrator_role(): void
    {
        Storage::fake('local');

        $this->stranka(User::factory()->withRole(UserRole::Recepce)->create())
            ->set('souborCertifikatu', $this->souborP12('tajne-heslo'))
            ->set('hesloCertifikatu', 'tajne-heslo')
            ->call('nahrajCertifikat')
            ->assertForbidden();

        $this->assertDatabaseCount('eet_certifikaty', 0);
    }

    public function test_connection_check_sends_a_verification_message_and_shows_success_with_warnings(): void
    {
        $client = $this->fakeClient()->odpovi(new Odpoved(
            uuidZpravy: 'b3a09b52-7c87-4014-a496-4c7a53cf9125',
            pok: null,
            test: true,
            varovani: [1 => 'EIC v datove zprave se neshoduje s EIC v certifikatu'],
            xmlPozadavku: '<Trzba/>',
            xmlOdpovedi: '<Odpoved/>',
        ));

        $this->stranka()
            ->call('overSpojeni')
            ->assertSee('Spojení je v pořádku')
            ->assertSee('Testovací prostředí EET zprávu přijalo.')
            ->assertSee('Varování 1: EIC v datove zprave se neshoduje s EIC v certifikatu');

        $this->assertCount(1, $client->odeslane);
        $this->assertTrue($client->odeslane[0]['overeni']);
    }

    public function test_connection_check_shows_the_error_reported_by_the_service(): void
    {
        $this->fakeClient()->odpovi(new EetChyba('Neplatny podpis zpravy', 4));

        $this->stranka()
            ->call('overSpojeni')
            ->assertSee('Ověření se nezdařilo')
            ->assertSee('Chyba 4: Neplatny podpis zpravy');
    }

    public function test_connection_check_explains_missing_certificate(): void
    {
        $this->fakeClient()->odpovi(NeuplneNastaveni::chybiCertifikat());

        $this->stranka()
            ->call('overSpojeni')
            ->assertSee('Ověření se nezdařilo')
            ->assertSee('Nejprve nahrajte pokladní certifikát.');
    }

    public function test_connection_check_is_forbidden_without_the_administrator_role(): void
    {
        $client = $this->fakeClient();

        $this->stranka(User::factory()->withRole(UserRole::Recepce)->create())
            ->call('overSpojeni')
            ->assertForbidden();

        $this->assertSame([], $client->odeslane);
    }

    private function spravce(): User
    {
        return User::factory()->withRole(UserRole::Spravce)->create();
    }

    /**
     * @return Testable<Component>
     */
    private function stranka(?User $user = null): Testable
    {
        return Livewire::actingAs($user ?? $this->spravce())->test('eet::nastaveni');
    }

    /**
     * @param  Testable<Component>  $stranka
     * @return Testable<Component>
     */
    private function vyplnena(Testable $stranka): Testable
    {
        return $stranka
            ->set('eic_popl', 'CZ683555118')
            ->set('nazev_provozovny', 'Estetic Ústí')
            ->set('adresa_provozovny', 'Masarykova 1, Ústí nad Labem')
            ->set('id_jednotky', '101')
            ->set('id_pokl', 'P1')
            ->set('prostredi', 'produkce')
            ->set('mezni_doba_odezvy', 5);
    }

    private function fakeClient(): FakeEetClient
    {
        $client = new FakeEetClient;
        $this->app->instance(EetClient::class, $client);

        return $client;
    }

    private function souborP12(string $heslo): UploadedFile
    {
        ['key' => $klic, 'cert' => $certifikat] = CertifikatFactory::testovaciKlicACertifikat();
        openssl_pkcs12_export($certifikat, $p12, $klic, $heslo);

        return UploadedFile::fake()->createWithContent('pokladna.p12', $p12);
    }
}
