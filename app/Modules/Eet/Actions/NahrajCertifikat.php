<?php

namespace App\Modules\Eet\Actions;

use App\Modules\Eet\Models\Certifikat;
use Eet2\Client;
use Eet2\EetException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class NahrajCertifikat
{
    /**
     * Store the key and certificate from a .p12 file as the active certificate; earlier ones are kept but deactivated.
     *
     * @throws NeplatnyCertifikat When the file cannot be opened with the password or holds no usable certificate.
     */
    public function handle(string $cestaKSouboruP12, string $heslo): Certifikat
    {
        try {
            ['key' => $privatniKlic, 'cert' => $certifikat] = Client::readP12($cestaKSouboruP12, $heslo);
        } catch (EetException $vyjimka) {
            throw new NeplatnyCertifikat('Certifikát se nepodařilo načíst. Zkontrolujte soubor a heslo.', previous: $vyjimka);
        }

        $udaje = openssl_x509_parse($certifikat);

        if ($udaje === false) {
            throw new NeplatnyCertifikat('Soubor neobsahuje čitelný certifikát.');
        }

        $bezneJmeno = $udaje['subject']['CN'] ?? '';

        return DB::transaction(function () use ($privatniKlic, $certifikat, $udaje, $bezneJmeno): Certifikat {
            Certifikat::query()->where('aktivni', true)->update(['aktivni' => false]);

            return Certifikat::create([
                'privatni_klic' => $privatniKlic,
                'certifikat' => $certifikat,
                'predmet' => is_array($bezneJmeno) ? implode(', ', $bezneJmeno) : (string) $bezneJmeno,
                'platnost_od' => Carbon::createFromTimestamp($udaje['validFrom_time_t'], config('app.timezone')),
                'platnost_do' => Carbon::createFromTimestamp($udaje['validTo_time_t'], config('app.timezone')),
                'aktivni' => true,
            ]);
        });
    }
}
