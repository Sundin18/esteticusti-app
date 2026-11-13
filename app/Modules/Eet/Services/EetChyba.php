<?php

namespace App\Modules\Eet\Services;

use RuntimeException;
use Throwable;

class EetChyba extends RuntimeException
{
    /**
     * The service is unreachable, timed out or reported a temporary error.
     */
    public const int KOD_DOCASNA = -1;

    /**
     * The confirmation's signature could not be verified, so its POK cannot be trusted.
     */
    public const int KOD_NEPLATNY_PODPIS = -900;

    /**
     * @param  int|null  $kod  EET error code, or null when the message could not even be built.
     */
    public function __construct(
        string $message,
        public readonly ?int $kod = null,
        public readonly ?string $xmlPozadavku = null,
        public readonly ?string $xmlOdpovedi = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $kod ?? 0, $previous);
    }

    /**
     * Whether the same sale should be sent again later instead of being corrected.
     */
    public function jeDocasna(): bool
    {
        return in_array($this->kod, [self::KOD_DOCASNA, self::KOD_NEPLATNY_PODPIS], true);
    }
}
