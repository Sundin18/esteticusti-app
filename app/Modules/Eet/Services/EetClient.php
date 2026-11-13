<?php

namespace App\Modules\Eet\Services;

interface EetClient
{
    /**
     * Send a sale to the EET service using the module's settings and active certificate.
     *
     * @param  bool  $prvniZaslani  False when the same sale is being sent again.
     * @param  bool  $overeni  True sends the message in verification mode: it is checked but not registered and no POK is issued.
     *
     * @throws EetChyba When the service rejects the message, cannot be reached or its confirmation cannot be trusted.
     * @throws NeuplneNastaveni When the settings or the certificate are missing.
     */
    public function odesli(UdajeTrzby $trzba, bool $prvniZaslani = true, bool $overeni = false): Odpoved;
}
