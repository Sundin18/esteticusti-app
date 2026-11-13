<?php

namespace App\Modules\Eet\Actions;

use App\Modules\Eet\Services\EetException;
use App\Modules\Eet\Services\EetClient;
use App\Modules\Eet\Services\IncompleteSetup;
use App\Modules\Eet\Services\EetResponse;
use App\Modules\Eet\Services\SaleData;

class VerifyConnection
{
    public function __construct(private EetClient $client) {}

    /**
     * Odešle zkušební tržbu v ověřovacím režimu: služba zkontroluje zprávu, podpis a údaje poplatníka, ale nic nezaeviduje.
     *
     * @throws EetException
     * @throws IncompleteSetup
     */
    public function handle(): EetResponse
    {
        return $this->client->send(
            new SaleData(receiptNumber: 'OVERENI', soldAt: now(), totalAmount: '1.00'),
            verifyOnly: true,
        );
    }
}
