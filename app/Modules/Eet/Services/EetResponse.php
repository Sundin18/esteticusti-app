<?php

namespace App\Modules\Eet\Services;

final readonly class EetResponse
{
    /**
     * @param  string|null  $pok  Potvrzovací kód; v ověřovacím režimu null.
     * @param  bool  $isTest  True, když odpověď přišla z testovacího prostředí.
     * @param  array<int, string>  $warnings  Texty varování podle kódu varování.
     * @param  string|null  $transactionId  Identifikátor transakce, který služba vrátila pro účely podpory.
     */
    public function __construct(
        public string $messageUuid,
        public ?string $pok,
        public bool $isTest,
        public array $warnings,
        public string $requestXml,
        public string $responseXml,
        public ?string $transactionId = null,
    ) {}
}
