<?php

namespace App\Modules\Eet\Actions;

use App\Modules\Eet\Services\EetChyba;
use App\Modules\Eet\Services\EetClient;
use App\Modules\Eet\Services\NeuplneNastaveni;
use App\Modules\Eet\Services\Odpoved;
use App\Modules\Eet\Services\UdajeTrzby;

class OverSpojeni
{
    public function __construct(private EetClient $client) {}

    /**
     * Send a sample sale in verification mode: the service checks the message, signature and taxpayer data but registers nothing.
     *
     * @throws EetChyba
     * @throws NeuplneNastaveni
     */
    public function handle(): Odpoved
    {
        return $this->client->odesli(
            new UdajeTrzby(poradCis: 'OVERENI', datTrzby: now(), celkTrzba: '1.00'),
            overeni: true,
        );
    }
}
