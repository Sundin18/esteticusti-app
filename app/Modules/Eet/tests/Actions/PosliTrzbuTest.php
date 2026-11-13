<?php

namespace App\Modules\Eet\tests\Actions;

use App\Modules\Eet\Actions\PosliTrzbu;
use App\Modules\Eet\Enums\StavTrzby;
use App\Modules\Eet\Models\Trzba;
use App\Modules\Eet\Models\Zprava;
use App\Modules\Eet\Services\EetChyba;
use App\Modules\Eet\Services\FakeEetClient;
use App\Modules\Eet\Services\NeuplneNastaveni;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PosliTrzbuTest extends TestCase
{
    use RefreshDatabase;

    public function test_another_attempt_is_marked_as_repeated_and_confirms_the_sale(): void
    {
        $client = new FakeEetClient;
        $trzba = Trzba::factory()->kOpakovani()->create(['porad_cis' => '7', 'celk_trzba' => '250.00']);
        Zprava::factory()->for($trzba)->sChybou(EetChyba::KOD_DOCASNA, 'Spojení s EET selhalo')->create();

        $trzba = (new PosliTrzbu($client))->handle($trzba);

        $this->assertSame(StavTrzby::Potvrzena, $trzba->stav);
        $this->assertNotNull($trzba->pok);
        $this->assertFalse($client->odeslane[0]['prvniZaslani']);
        $this->assertSame('7', $client->odeslane[0]['trzba']->poradCis);
        $this->assertSame('250.00', $client->odeslane[0]['trzba']->celkTrzba);
        $this->assertSame($trzba->dat_trzby->getTimestamp(), $client->odeslane[0]['trzba']->datTrzby->getTimestamp());
        $this->assertCount(2, $trzba->zpravy);
        $this->assertFalse($trzba->zpravy->last()->prvni_zaslani);
    }

    public function test_confirmed_sale_is_not_sent_again(): void
    {
        $client = new FakeEetClient;
        $trzba = Trzba::factory()->potvrzena()->create();
        $pok = $trzba->pok;

        $trzba = (new PosliTrzbu($client))->handle($trzba);

        $this->assertSame([], $client->odeslane);
        $this->assertSame($pok, $trzba->pok);
    }

    public function test_sale_confirmed_by_another_process_in_the_meantime_is_not_sent_again(): void
    {
        $client = new FakeEetClient;
        $trzba = Trzba::factory()->kOpakovani()->create();
        Trzba::query()->whereKey($trzba->id)->update(['stav' => StavTrzby::Potvrzena, 'pok' => 'pok-z-jineho-procesu']);

        $trzba = (new PosliTrzbu($client))->handle($trzba);

        $this->assertSame([], $client->odeslane);
        $this->assertSame('pok-z-jineho-procesu', $trzba->pok);
    }

    public function test_sale_being_sent_by_another_process_is_skipped(): void
    {
        $client = new FakeEetClient;
        $trzba = Trzba::factory()->kOpakovani()->create();
        Cache::lock("eet:trzba:{$trzba->id}", 120)->get();

        (new PosliTrzbu($client))->handle($trzba);

        $this->assertSame([], $client->odeslane);
        $this->assertDatabaseCount('eet_zpravy', 0);
    }

    public function test_rejection_after_a_repeated_attempt_marks_the_sale_as_rejected(): void
    {
        $client = (new FakeEetClient)->odpovi(new EetChyba('Neplatny podpis', 4, '<pozadavek/>', '<odpoved/>', 'b3a09b52-7c87-4014-a496-4c7a53cf9125'));
        $trzba = Trzba::factory()->kOpakovani()->create();

        $trzba = (new PosliTrzbu($client))->handle($trzba);

        $zprava = $trzba->zpravy()->sole();

        $this->assertSame(StavTrzby::Zamitnuta, $trzba->stav);
        $this->assertSame(4, $zprava->kod_chyby);
        $this->assertSame('b3a09b52-7c87-4014-a496-4c7a53cf9125', $zprava->uuid_zpravy);
        $this->assertFalse($zprava->jeUspesna());
    }

    public function test_missing_settings_leave_the_sale_unchanged_and_free_for_the_next_attempt(): void
    {
        $client = (new FakeEetClient)->odpovi(NeuplneNastaveni::chybiCertifikat());
        $trzba = Trzba::factory()->kOpakovani()->create();

        try {
            (new PosliTrzbu($client))->handle($trzba);
            $this->fail('Chybějící certifikát měl skončit výjimkou.');
        } catch (NeuplneNastaveni) {
            $this->assertSame(StavTrzby::KOpakovani, $trzba->refresh()->stav);
            $this->assertDatabaseCount('eet_zpravy', 0);
        }

        $this->assertSame(StavTrzby::Potvrzena, (new PosliTrzbu($client))->handle($trzba)->stav);
    }
}
