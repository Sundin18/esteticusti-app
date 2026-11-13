<?php

namespace App\Modules\Eet\Actions;

use App\Modules\Eet\Models\Certificate;
use Eet2\Client;
use Eet2\EetException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UploadCertificate
{
    /**
     * Uloží klíč a certifikát ze souboru .p12 jako aktivní certifikát; dřívější zůstanou uložené, ale neaktivní.
     *
     * @throws InvalidCertificate Když soubor nejde heslem otevřít nebo neobsahuje použitelný certifikát.
     */
    public function handle(string $p12Path, string $password): Certificate
    {
        try {
            ['key' => $privateKey, 'cert' => $certificate] = Client::readP12($p12Path, $password);
        } catch (EetException $exception) {
            throw new InvalidCertificate('Certifikát se nepodařilo načíst. Zkontrolujte soubor a heslo.', previous: $exception);
        }

        $data = openssl_x509_parse($certificate);

        if ($data === false) {
            throw new InvalidCertificate('Soubor neobsahuje čitelný certifikát.');
        }

        $commonName = $data['subject']['CN'] ?? '';

        return DB::transaction(function () use ($privateKey, $certificate, $data, $commonName): Certificate {
            Certificate::query()->where('is_active', true)->update(['is_active' => false]);

            return Certificate::create([
                'private_key' => $privateKey,
                'certificate' => $certificate,
                'subject' => is_array($commonName) ? implode(', ', $commonName) : (string) $commonName,
                'valid_from' => Carbon::createFromTimestamp($data['validFrom_time_t'], config('app.timezone')),
                'valid_until' => Carbon::createFromTimestamp($data['validTo_time_t'], config('app.timezone')),
                'is_active' => true,
            ]);
        });
    }
}
