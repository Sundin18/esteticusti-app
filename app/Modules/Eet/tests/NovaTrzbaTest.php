<?php

namespace App\Modules\Eet\tests;

use App\Models\User;
use App\Modules\Eet\Enums\StavTrzby;
use App\Modules\Eet\Models\Certifikat;
use App\Modules\Eet\Models\Nastaveni;
use App\Modules\Eet\Models\Trzba;
use App\Modules\Eet\Services\EetChyba;
use App\Modules\Eet\Services\EetClient;
use App\Modules\Eet\Services\FakeEetClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class NovaTrzbaTest extends TestCase
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
        $this->get(route('eet.trzby.nova'))->assertRedirect(route('login'));
    }

    public function test_signed_in_user_sees_the_form(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/eet/trzby/nova')->assertOk()->assertSeeText('Zaevidovat tržbu');
    }

    public function test_user_records_a_sale_and_sees_its_pok(): void
    {
        $this->nastavModul();
        $user = User::factory()->create();

        $stranka = Livewire::actingAs($user)
            ->test('eet::nova-trzba')
            ->set('castka', '1 500,50')
            ->call('zaeviduj')
            ->assertHasNoErrors()
            ->assertSet('castka', '');

        $trzba = Trzba::query()->sole();

        $this->assertSame('1500.50', $trzba->celk_trzba);
        $this->assertSame(StavTrzby::Potvrzena, $trzba->stav);
        $this->assertTrue($trzba->user->is($user));

        $stranka
            ->assertSee('potvrzena')
            ->assertSee("POK: {$trzba->pok}")
            ->assertSeeHtml(route('eet.trzby.uctenka', $trzba));
    }

    public function test_unavailable_service_tells_the_user_the_sale_will_be_sent_later(): void
    {
        $this->nastavModul();
        $this->client->odpovi(new EetChyba('Spojení s EET selhalo', EetChyba::KOD_DOCASNA));

        Livewire::actingAs(User::factory()->create())
            ->test('eet::nova-trzba')
            ->set('castka', '100')
            ->call('zaeviduj')
            ->assertSee('EET je nedostupná')
            ->assertSee('Tržba se odešle automaticky');
    }

    public function test_rejected_sale_shows_the_reason_and_offers_no_receipt(): void
    {
        $this->nastavModul();
        $this->client->odpovi(new EetChyba('Neplatny EIC', 6));

        Livewire::actingAs(User::factory()->create())
            ->test('eet::nova-trzba')
            ->set('castka', '100')
            ->call('zaeviduj')
            ->assertSee('zamítnuta systémem EET')
            ->assertSee('Neplatny EIC')
            ->assertDontSeeHtml('data-test="uctenka"');
    }

    #[TestWith([''])]
    #[TestWith(['0'])]
    #[TestWith(['-50'])]
    #[TestWith(['12,345'])]
    #[TestWith(['sto korun'])]
    #[TestWith(['100000000'])]
    public function test_invalid_amount_is_rejected(string $castka): void
    {
        $this->nastavModul();

        Livewire::actingAs(User::factory()->create())
            ->test('eet::nova-trzba')
            ->set('castka', $castka)
            ->call('zaeviduj')
            ->assertHasErrors('castka');

        $this->assertDatabaseCount('eet_trzby', 0);
    }

    public function test_missing_certificate_is_explained_to_the_user(): void
    {
        Nastaveni::factory()->create();

        Livewire::actingAs(User::factory()->create())
            ->test('eet::nova-trzba')
            ->set('castka', '100')
            ->call('zaeviduj')
            ->assertHasErrors('castka')
            ->assertSee('Nejprve nahrajte pokladní certifikát.');

        $this->assertDatabaseCount('eet_trzby', 0);
    }

    private function nastavModul(): void
    {
        Nastaveni::factory()->create();
        Certifikat::factory()->create();
    }
}
