<?php

namespace App\Modules\Eet\Services;

use App\Modules\Eet\Models\Certifikat;
use App\Modules\Eet\Models\Nastaveni;
use Eet2\Client;
use Eet2\EetException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Builds, signs and verifies messages with the jakubsilny/eet2 library and sends them over Laravel's HTTP client.
 */
class SoapEetClient implements EetClient
{
    public function odesli(UdajeTrzby $trzba, bool $prvniZaslani = true, bool $overeni = false): Odpoved
    {
        $nastaveni = Nastaveni::aktualni() ?? throw NeuplneNastaveni::chybiNastaveni();
        $certifikat = Certifikat::aktivni() ?? throw NeuplneNastaveni::chybiCertifikat();

        $uuidZpravy = (string) Str::uuid();
        $xmlPozadavku = $this->sestavZpravu($nastaveni, $certifikat, $trzba, $uuidZpravy, $prvniZaslani, $overeni);

        try {
            $odpoved = Http::timeout($nastaveni->mezni_doba_odezvy)
                ->withHeaders(['SOAPAction' => '"http://fs.gov.cz/eet/OdeslaniTrzby"'])
                ->withBody($xmlPozadavku, 'text/xml; charset=utf-8')
                ->post($nastaveni->prostredi->url());
        } catch (ConnectionException $vyjimka) {
            throw new EetChyba(
                'Spojení s EET selhalo: '.$vyjimka->getMessage(),
                EetChyba::KOD_DOCASNA,
                $xmlPozadavku,
                uuidZpravy: $uuidZpravy,
                previous: $vyjimka,
            );
        }

        $xmlOdpovedi = $odpoved->body();
        $idTransakce = $odpoved->header('X-Global-Transaction-Id') ?: null;

        try {
            $vysledek = Client::parseResponse($xmlOdpovedi, $odpoved->status(), $uuidZpravy, $idTransakce, $overeni);

            if ($vysledek['pok'] !== null) {
                Client::verifyResponseSignature($xmlOdpovedi);
            }
        } catch (EetException $vyjimka) {
            throw new EetChyba($vyjimka->getMessage(), $vyjimka->getCode(), $xmlPozadavku, $xmlOdpovedi, $uuidZpravy, $vyjimka);
        }

        return new Odpoved(
            uuidZpravy: $uuidZpravy,
            pok: $vysledek['pok'],
            test: $vysledek['test'],
            varovani: $vysledek['warnings'],
            xmlPozadavku: $xmlPozadavku,
            xmlOdpovedi: $xmlOdpovedi,
            idTransakce: $idTransakce,
        );
    }

    /**
     * Build the signed SOAP message; data or certificate problems surface before anything is sent.
     */
    protected function sestavZpravu(
        Nastaveni $nastaveni,
        Certifikat $certifikat,
        UdajeTrzby $trzba,
        string $uuidZpravy,
        bool $prvniZaslani,
        bool $overeni,
    ): string {
        try {
            return new Client($certifikat->privatni_klic, $certifikat->certifikat)->buildSignedMessage([
                'eic_popl' => $nastaveni->eic_popl,
                'id_jednotky' => $nastaveni->id_jednotky,
                'id_pokl' => $nastaveni->id_pokl,
                'porad_cis' => $trzba->poradCis,
                'dat_trzby' => $trzba->datTrzby,
                'celk_trzba' => $trzba->celkTrzba,
            ], $uuidZpravy, $prvniZaslani, $overeni);
        } catch (EetException $vyjimka) {
            throw new EetChyba($vyjimka->getMessage(), previous: $vyjimka);
        }
    }
}
