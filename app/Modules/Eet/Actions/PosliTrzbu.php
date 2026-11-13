<?php

namespace App\Modules\Eet\Actions;

use App\Modules\Eet\Enums\StavTrzby;
use App\Modules\Eet\Models\Trzba;
use App\Modules\Eet\Services\EetChyba;
use App\Modules\Eet\Services\EetClient;
use App\Modules\Eet\Services\NeuplneNastaveni;
use App\Modules\Eet\Services\UdajeTrzby;
use Illuminate\Support\Facades\Cache;

class PosliTrzbu
{
    public function __construct(private EetClient $client) {}

    /**
     * Make one attempt to send the sale, record it and move the sale to the resulting state.
     *
     * A confirmed sale, or one another process is sending right now, is returned untouched.
     *
     * @throws NeuplneNastaveni When the settings or the certificate are missing; nothing is recorded.
     */
    public function handle(Trzba $trzba): Trzba
    {
        $zamek = Cache::lock("eet:trzba:{$trzba->id}", 120);

        if (! $zamek->get()) {
            return $trzba;
        }

        try {
            $trzba->refresh();

            if ($trzba->jePotvrzena()) {
                return $trzba;
            }

            $this->odesli($trzba);
        } finally {
            $zamek->release();
        }

        return $trzba;
    }

    protected function odesli(Trzba $trzba): void
    {
        $prvniZaslani = $trzba->zpravy()->doesntExist();
        $datOdesl = now();

        try {
            $odpoved = $this->client->odesli(
                new UdajeTrzby($trzba->porad_cis, $trzba->dat_trzby, $trzba->celk_trzba),
                prvniZaslani: $prvniZaslani,
            );
        } catch (EetChyba $chyba) {
            $trzba->zpravy()->create([
                'uuid_zpravy' => $chyba->uuidZpravy,
                'dat_odesl' => $datOdesl,
                'prvni_zaslani' => $prvniZaslani,
                'xml_pozadavku' => $chyba->xmlPozadavku,
                'xml_odpovedi' => $chyba->xmlOdpovedi,
                'kod_chyby' => $chyba->kod,
                'chyba' => $chyba->getMessage(),
            ]);

            $trzba->update(['stav' => $chyba->jeDocasna() ? StavTrzby::KOpakovani : StavTrzby::Zamitnuta]);

            return;
        }

        $trzba->zpravy()->create([
            'uuid_zpravy' => $odpoved->uuidZpravy,
            'dat_odesl' => $datOdesl,
            'prvni_zaslani' => $prvniZaslani,
            'xml_pozadavku' => $odpoved->xmlPozadavku,
            'xml_odpovedi' => $odpoved->xmlOdpovedi,
            'varovani' => $odpoved->varovani ?: null,
        ]);

        $trzba->update([
            'stav' => StavTrzby::Potvrzena,
            'pok' => $odpoved->pok,
            'testovaci' => $odpoved->test,
        ]);
    }
}
