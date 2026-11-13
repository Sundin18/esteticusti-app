<?php

namespace App\Modules\Eet\Actions;

use App\Modules\Eet\Models\Settings;
use App\Modules\Eet\Services\EetClient;
use App\Modules\Eet\Services\EetException;
use App\Modules\Eet\Services\EetResponse;
use App\Modules\Eet\Services\IncompleteSetup;
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
        $settings = Settings::current() ?? throw IncompleteSetup::missingSettings();

        return $this->client->send(
            new SaleData(
                taxpayerEic: $settings->taxpayer_eic,
                unitId: $settings->unit_id,
                registerId: $settings->register_id,
                receiptNumber: 'OVERENI',
                soldAt: now(),
                totalAmount: '1.00',
            ),
            verifyOnly: true,
        );
    }
}
