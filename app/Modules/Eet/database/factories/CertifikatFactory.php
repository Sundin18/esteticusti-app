<?php

namespace App\Modules\Eet\database\factories;

use App\Modules\Eet\Models\Certifikat;
use Illuminate\Database\Eloquent\Factories\Factory;
use RuntimeException;

/**
 * @extends Factory<Certifikat>
 */
class CertifikatFactory extends Factory
{
    protected $model = Certifikat::class;

    /**
     * The self-signed key pair shared by all generated certificates.
     *
     * @var array{key: string, cert: string}|null
     */
    protected static ?array $klicACertifikat = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        ['key' => $klic, 'cert' => $certifikat] = static::testovaciKlicACertifikat();

        return [
            'privatni_klic' => $klic,
            'certifikat' => $certifikat,
            'predmet' => 'CZ00000019',
            'platnost_od' => now()->subMonth(),
            'platnost_do' => now()->addMonths(11),
            'aktivni' => true,
        ];
    }

    /**
     * Indicate that the certificate has been replaced by a newer one.
     */
    public function neaktivni(): static
    {
        return $this->state(fn (array $attributes) => [
            'aktivni' => false,
        ]);
    }

    /**
     * Indicate that the certificate's validity has ended.
     */
    public function vyprsely(): static
    {
        return $this->state(fn (array $attributes) => [
            'platnost_od' => now()->subYears(2),
            'platnost_do' => now()->subYear(),
        ]);
    }

    /**
     * Get a real, self-signed key and certificate in PEM that can sign messages in tests.
     *
     * @return array{key: string, cert: string}
     */
    public static function testovaciKlicACertifikat(): array
    {
        if (static::$klicACertifikat !== null) {
            return static::$klicACertifikat;
        }

        $klic = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

        if ($klic === false) {
            throw new RuntimeException('Testovací klíč se nepodařilo vygenerovat.');
        }

        $zadost = openssl_csr_new(['commonName' => 'CZ00000019'], $klic, ['digest_alg' => 'sha256']);

        if (! $zadost instanceof \OpenSSLCertificateSigningRequest) {
            throw new RuntimeException('Testovací certifikát se nepodařilo vygenerovat.');
        }

        $certifikat = openssl_csr_sign($zadost, null, $klic, 365, ['digest_alg' => 'sha256']);

        if ($certifikat === false
            || ! openssl_pkey_export($klic, $klicPem)
            || ! openssl_x509_export($certifikat, $certifikatPem)) {
            throw new RuntimeException('Testovací certifikát se nepodařilo vygenerovat.');
        }

        return static::$klicACertifikat = ['key' => $klicPem, 'cert' => $certifikatPem];
    }
}
