<?php

namespace App\Modules\Eet\database\factories;

use App\Modules\Eet\Models\Certificate;
use Illuminate\Database\Eloquent\Factories\Factory;
use RuntimeException;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    /**
     * Klíč a certifikát podepsaný sám sebou, sdílený všemi vygenerovanými certifikáty.
     *
     * @var array{key: string, cert: string}|null
     */
    protected static ?array $keyAndCertificate = null;

    /**
     * Výchozí stav modelu.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        ['key' => $key, 'cert' => $certificate] = static::testKeyAndCertificate();

        return [
            'private_key' => $key,
            'certificate' => $certificate,
            'subject' => 'CZ00000019',
            'valid_from' => now()->subMonth(),
            'valid_until' => now()->addMonths(11),
            'is_active' => true,
        ];
    }

    /**
     * Certifikát byl nahrazen novějším.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Platnost certifikátu už skončila.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_from' => now()->subYears(2),
            'valid_until' => now()->subYear(),
        ]);
    }

    /**
     * Vrátí skutečný klíč a certifikát podepsaný sám sebou ve formátu PEM, kterým lze v testech podepisovat zprávy.
     *
     * @return array{key: string, cert: string}
     */
    public static function testKeyAndCertificate(): array
    {
        if (static::$keyAndCertificate !== null) {
            return static::$keyAndCertificate;
        }

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

        if ($key === false) {
            throw new RuntimeException('The test key could not be generated.');
        }

        $csr = openssl_csr_new(['commonName' => 'CZ00000019'], $key, ['digest_alg' => 'sha256']);

        if (! $csr instanceof \OpenSSLCertificateSigningRequest) {
            throw new RuntimeException('The test certificate could not be generated.');
        }

        $certificate = openssl_csr_sign($csr, null, $key, 365, ['digest_alg' => 'sha256']);

        if ($certificate === false
            || ! openssl_pkey_export($key, $keyPem)
            || ! openssl_x509_export($certificate, $certificatePem)) {
            throw new RuntimeException('The test certificate could not be generated.');
        }

        return static::$keyAndCertificate = ['key' => $keyPem, 'cert' => $certificatePem];
    }
}
