<?php

namespace App\Modules\Eet\Services;

interface EetClient
{
    /**
     * Odešle tržbu službě EET s nastavením modulu a aktivním certifikátem.
     *
     * @param  bool  $firstAttempt  False, když se stejná tržba posílá znovu.
     * @param  bool  $verifyOnly  True odešle zprávu v ověřovacím režimu: zkontroluje se, ale neeviduje a POK se nevydá.
     *
     * @throws EetException Když služba zprávu odmítne, je nedostupná nebo jejímu potvrzení nelze věřit.
     * @throws IncompleteSetup Když chybí nastavení nebo certifikát.
     */
    public function send(SaleData $sale, bool $firstAttempt = true, bool $verifyOnly = false): EetResponse;
}
