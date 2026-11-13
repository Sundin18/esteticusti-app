<?php

namespace App\Modules\Eet\Services;

use RuntimeException;

class NeuplneNastaveni extends RuntimeException
{
    public static function chybiNastaveni(): self
    {
        return new self('Nejprve vyplňte a uložte údaje poplatníka.');
    }

    public static function chybiCertifikat(): self
    {
        return new self('Nejprve nahrajte pokladní certifikát.');
    }
}
