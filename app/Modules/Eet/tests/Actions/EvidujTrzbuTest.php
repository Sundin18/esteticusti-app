<?php

namespace App\Modules\Eet\tests\Actions;

use App\Models\User;
use App\Modules\Eet\Actions\EvidujTrzbu;
use App\Modules\Eet\Enums\StavTrzby;
use App\Modules\Eet\Jobs\OdesliTrzbu;
use App\Modules\Eet\Models\Certifikat;
use App\Modules\Eet\Models\Nastaveni;
use App\Modules\Eet\Models\Trzba;
use App\Modules\Eet\Services\EetChyba;
use App\Modules\Eet\Services\EetClient;
use App\Modules\Eet\Services\FakeEetClient;
use App\Modules\Eet\Services\NeuplneNastaveni;
use App\Modules\Eet\Services\Odpoved;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EvidujTrzbuTest extends TestCase
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

    public function test_confirmed_sale_stores_the_pok_and_the_attempt(): void
    {
        $this->freezeSecond();
        $this->nastavModul();
        $user = User::factory()->create();
        $this->client->odpovi(new Odpoved(
            uuidZpravy: 'b3a09b52-7c87-4014-a496-4c7a53cf9125',
            pok: '987a6be5-6af5-44f3-b4fc-987654321000-ff',
            test: true,
            varovani: [1 => 'EIC se neshoduje s certifikatem'],
            xmlPozadavku: '<pozadavek/>',
            xmlOdpovedi: '<odpoved/>',
        ));

        $trzba = $this->eviduj($user, '1500.50');

        $this->assertSame(StavTrzby::Potvrzena, $trzba->stav);
        $this->assertSame('987a6be5-6af5-44f3-b4fc-987654321000-ff', $trzba->pok);
        $this->assertTrue($trzba->testovaci);
        $this->assertSame('1500.50', $trzba->celk_trzba);
        $this->assertTrue($trzba->dat_trzby->equalTo(now()));
        $this->assertTrue($trzba->user->is($user));

        $zprava = $trzba->zpravy()->sole();

        $this->assertSame('b3a09b52-7c87-4014-a496-4c7a53cf9125', $zprava->uuid_zpravy);
        $this->assertTrue($zprava->prvni_zaslani);
        $this->assertSame('<pozadavek/>', $zprava->xml_pozadavku);
        $this->assertSame('<odpoved/>', $zprava->xml_odpovedi);
        $this->assertSame([1 => 'EIC se neshoduje s certifikatem'], $zprava->varovani);
        $this->assertTrue($zprava->jeUspesna());

        $this->assertSame('1500.50', $this->client->odeslane[0]['trzba']->celkTrzba);
        $this->assertTrue($this->client->odeslane[0]['prvniZaslani']);
        $this->assertFalse($this->client->odeslane[0]['overeni']);
        Queue::assertNothingPushed();
    }

    public function test_sales_get_consecutive_receipt_numbers(): void
    {
        $this->nastavModul(['posledni_porad_cis' => 41]);
        $user = User::factory()->create();

        $prvni = $this->eviduj($user, '100.00');
        $druha = $this->eviduj($user, '200.00');

        $this->assertSame('42', $prvni->porad_cis);
        $this->assertSame('43', $druha->porad_cis);
        $this->assertSame(43, Nastaveni::query()->sole()->posledni_porad_cis);
    }

    public function test_unavailable_service_keeps_the_sale_and_queues_another_attempt(): void
    {
        $this->freezeSecond();
        $this->nastavModul();
        $this->client->odpovi(new EetChyba('Spojení s EET selhalo', EetChyba::KOD_DOCASNA, '<pozadavek/>'));

        $trzba = $this->eviduj(User::factory()->create(), '100.00');

        $this->assertSame(StavTrzby::KOpakovani, $trzba->stav);
        $this->assertNull($trzba->pok);
        $this->assertSame(EetChyba::KOD_DOCASNA, $trzba->zpravy()->sole()->kod_chyby);
        Queue::assertPushed(
            OdesliTrzbu::class,
            fn (OdesliTrzbu $job): bool => $job->trzba->is($trzba)
                && $job->delay instanceof DateTimeInterface
                && now()->addMinute()->equalTo($job->delay),
        );
    }

    public function test_rejected_sale_is_not_queued(): void
    {
        $this->nastavModul();
        $this->client->odpovi(new EetChyba('Neplatny EIC', 6, '<pozadavek/>', '<odpoved/>'));

        $trzba = $this->eviduj(User::factory()->create(), '100.00');

        $this->assertSame(StavTrzby::Zamitnuta, $trzba->stav);
        $this->assertSame('Neplatny EIC', $trzba->zpravy()->sole()->chyba);
        Queue::assertNothingPushed();
    }

    public function test_missing_settings_record_nothing(): void
    {
        Certifikat::factory()->create();

        try {
            $this->eviduj(User::factory()->create(), '100.00');
            $this->fail('Bez nastavení měla evidence skončit výjimkou.');
        } catch (NeuplneNastaveni) {
            $this->assertDatabaseCount('eet_trzby', 0);
            $this->assertSame([], $this->client->odeslane);
        }
    }

    public function test_missing_certificate_records_nothing_and_uses_no_receipt_number(): void
    {
        Nastaveni::factory()->create();

        try {
            $this->eviduj(User::factory()->create(), '100.00');
            $this->fail('Bez certifikátu měla evidence skončit výjimkou.');
        } catch (NeuplneNastaveni) {
            $this->assertDatabaseCount('eet_trzby', 0);
            $this->assertSame(0, Nastaveni::query()->sole()->posledni_porad_cis);
        }
    }

    /**
     * @param  array<string, mixed>  $nastaveni
     */
    private function nastavModul(array $nastaveni = []): void
    {
        Nastaveni::factory()->create()->forceFill($nastaveni)->save();
        Certifikat::factory()->create();
    }

    private function eviduj(User $user, string $castka): Trzba
    {
        return $this->app->make(EvidujTrzbu::class)->handle($user, $castka);
    }
}
