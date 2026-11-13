<?php

namespace App\Modules\Eet\tests;

use App\Core\Authorization\UserRole;
use App\Models\User;
use App\Modules\Eet\database\factories\CertificateFactory;
use App\Modules\Eet\Enums\Environment;
use App\Modules\Eet\Models\Certificate;
use App\Modules\Eet\Models\Settings;
use App\Modules\Eet\Services\EetClient;
use App\Modules\Eet\Services\EetException;
use App\Modules\Eet\Services\EetResponse;
use App\Modules\Eet\Services\FakeEetClient;
use App\Modules\Eet\Services\IncompleteSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_login_page(): void
    {
        $this->get(route('eet.settings'))->assertRedirect(route('login'));
    }

    public function test_settings_are_forbidden_without_the_administrator_role(): void
    {
        $this->actingAs(User::factory()->withRole(UserRole::Reception)->create());

        $this->get(route('eet.settings'))->assertForbidden();
    }

    public function test_administrator_sees_the_saved_settings(): void
    {
        Settings::factory()->create(['taxpayer_eic' => 'CZ683555118', 'premises_name' => 'Estetic Ústí']);
        $this->actingAs($this->admin());

        $this->get('/eet/settings')->assertOk();

        $this->page()
            ->assertSet('taxpayer_eic', 'CZ683555118')
            ->assertSet('premises_name', 'Estetic Ústí')
            ->assertSet('environment', 'test');
    }

    public function test_administrator_saves_the_settings(): void
    {
        $this->filledIn($this->page())
            ->call('saveSettings')
            ->assertHasNoErrors();

        $settings = Settings::query()->sole();

        $this->assertSame('CZ683555118', $settings->taxpayer_eic);
        $this->assertSame('Estetic Ústí', $settings->premises_name);
        $this->assertSame('Masarykova 1, Ústí nad Labem', $settings->premises_address);
        $this->assertSame(101, $settings->unit_id);
        $this->assertSame('P1', $settings->register_id);
        $this->assertSame(Environment::Production, $settings->environment);
        $this->assertSame(5, $settings->response_timeout);
    }

    public function test_saving_again_updates_the_single_settings_row_and_keeps_the_receipt_counter(): void
    {
        Settings::factory()->create()->forceFill(['last_receipt_number' => 42])->save();

        $this->filledIn($this->page())->call('saveSettings')->assertHasNoErrors();

        $settings = Settings::query()->sole();

        $this->assertSame('CZ683555118', $settings->taxpayer_eic);
        $this->assertSame(42, $settings->last_receipt_number);
    }

    public function test_values_outside_the_eet_formats_are_rejected(): void
    {
        $this->filledIn($this->page())
            ->set('taxpayer_eic', '683555118')
            ->set('unit_id', '0')
            ->set('register_id', 'pokladna č. 1')
            ->set('environment', 'vyvoj')
            ->set('response_timeout', 1)
            ->call('saveSettings')
            ->assertHasErrors(['taxpayer_eic', 'unit_id', 'register_id', 'environment', 'response_timeout']);

        $this->assertDatabaseCount('eet_settings', 0);
    }

    public function test_saving_is_forbidden_without_the_administrator_role(): void
    {
        $this->filledIn($this->page(User::factory()->withRole(UserRole::Therapist)->create()))
            ->call('saveSettings')
            ->assertForbidden();

        $this->assertDatabaseCount('eet_settings', 0);
    }

    public function test_administrator_uploads_a_certificate(): void
    {
        Storage::fake('local');

        $this->page()
            ->set('certificateFile', $this->p12File('tajne-heslo'))
            ->set('certificatePassword', 'tajne-heslo')
            ->call('uploadCertificate')
            ->assertHasNoErrors()
            ->assertSet('certificatePassword', '')
            ->assertSee('CZ00000019');

        $this->assertSame('CZ00000019', Certificate::active()?->subject);
    }

    public function test_certificate_with_a_wrong_password_is_rejected(): void
    {
        Storage::fake('local');

        $this->page()
            ->set('certificateFile', $this->p12File('tajne-heslo'))
            ->set('certificatePassword', 'spatne-heslo')
            ->call('uploadCertificate')
            ->assertHasErrors('certificateFile');

        $this->assertDatabaseCount('eet_certificates', 0);
    }

    public function test_uploading_is_forbidden_without_the_administrator_role(): void
    {
        Storage::fake('local');

        $this->page(User::factory()->withRole(UserRole::Reception)->create())
            ->set('certificateFile', $this->p12File('tajne-heslo'))
            ->set('certificatePassword', 'tajne-heslo')
            ->call('uploadCertificate')
            ->assertForbidden();

        $this->assertDatabaseCount('eet_certificates', 0);
    }

    public function test_connection_check_sends_a_verification_message_and_shows_success_with_warnings(): void
    {
        Settings::factory()->create(['taxpayer_eic' => 'CZ683555118']);
        $client = $this->fakeClient()->respondWith(new EetResponse(
            messageUuid: 'b3a09b52-7c87-4014-a496-4c7a53cf9125',
            pok: null,
            isTest: true,
            warnings: [1 => 'EIC v datove zprave se neshoduje s EIC v certifikatu'],
            requestXml: '<Trzba/>',
            responseXml: '<Odpoved/>',
        ));

        $this->page()
            ->call('verifyConnection')
            ->assertSee('Spojení je v pořádku')
            ->assertSee('Testovací prostředí EET zprávu přijalo.')
            ->assertSee('Varování 1: EIC v datove zprave se neshoduje s EIC v certifikatu');

        $this->assertCount(1, $client->sent);
        $this->assertTrue($client->sent[0]['verifyOnly']);
        $this->assertSame('CZ683555118', $client->sent[0]['sale']->taxpayerEic);
    }

    public function test_connection_check_shows_the_error_reported_by_the_service(): void
    {
        Settings::factory()->create();
        $this->fakeClient()->respondWith(new EetException('Neplatny podpis zpravy', 4));

        $this->page()
            ->call('verifyConnection')
            ->assertSee('Ověření se nezdařilo')
            ->assertSee('Chyba 4: Neplatny podpis zpravy');
    }

    public function test_connection_check_explains_missing_certificate(): void
    {
        Settings::factory()->create();
        $this->fakeClient()->respondWith(IncompleteSetup::missingCertificate());

        $this->page()
            ->call('verifyConnection')
            ->assertSee('Ověření se nezdařilo')
            ->assertSee('Nejprve nahrajte pokladní certifikát.');
    }

    public function test_connection_check_explains_missing_settings_without_sending_anything(): void
    {
        $client = $this->fakeClient();

        $this->page()
            ->call('verifyConnection')
            ->assertSee('Ověření se nezdařilo')
            ->assertSee('Nejprve vyplňte a uložte údaje poplatníka.');

        $this->assertSame([], $client->sent);
    }

    public function test_connection_check_is_forbidden_without_the_administrator_role(): void
    {
        $client = $this->fakeClient();

        $this->page(User::factory()->withRole(UserRole::Reception)->create())
            ->call('verifyConnection')
            ->assertForbidden();

        $this->assertSame([], $client->sent);
    }

    private function admin(): User
    {
        return User::factory()->withRole(UserRole::Admin)->create();
    }

    /**
     * @return Testable<Component>
     */
    private function page(?User $user = null): Testable
    {
        return Livewire::actingAs($user ?? $this->admin())->test('eet::settings');
    }

    /**
     * @param  Testable<Component>  $page
     * @return Testable<Component>
     */
    private function filledIn(Testable $page): Testable
    {
        return $page
            ->set('taxpayer_eic', 'CZ683555118')
            ->set('premises_name', 'Estetic Ústí')
            ->set('premises_address', 'Masarykova 1, Ústí nad Labem')
            ->set('unit_id', '101')
            ->set('register_id', 'P1')
            ->set('environment', 'production')
            ->set('response_timeout', 5);
    }

    private function fakeClient(): FakeEetClient
    {
        $client = new FakeEetClient;
        $this->app->instance(EetClient::class, $client);

        return $client;
    }

    private function p12File(string $password): UploadedFile
    {
        ['key' => $key, 'cert' => $certificate] = CertificateFactory::testKeyAndCertificate();
        openssl_pkcs12_export($certificate, $p12, $key, $password);

        return UploadedFile::fake()->createWithContent('pokladna.p12', $p12);
    }
}
