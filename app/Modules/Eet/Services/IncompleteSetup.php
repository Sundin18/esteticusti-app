<?php

namespace App\Modules\Eet\Services;

use RuntimeException;

class IncompleteSetup extends RuntimeException
{
    public static function missingSettings(): self
    {
        return new self('Nejprve vyplňte a uložte údaje poplatníka.');
    }

    public static function missingCertificate(): self
    {
        return new self('Nejprve nahrajte pokladní certifikát.');
    }
}
