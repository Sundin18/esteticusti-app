<?php

namespace App\Modules\Eet\Services;

final readonly class Odpoved
{
    /**
     * @param  string|null  $pok  Confirmation code; null in verification mode.
     * @param  bool  $test  True when the response comes from the test environment.
     * @param  array<int, string>  $varovani  Warning texts keyed by warning code.
     * @param  string|null  $idTransakce  Transaction identifier the service returned for support requests.
     */
    public function __construct(
        public string $uuidZpravy,
        public ?string $pok,
        public bool $test,
        public array $varovani,
        public string $xmlPozadavku,
        public string $xmlOdpovedi,
        public ?string $idTransakce = null,
    ) {}
}
