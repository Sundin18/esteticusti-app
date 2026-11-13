<?php

namespace App\Modules\Eet\Services;

use RuntimeException;
use Throwable;

class EetException extends RuntimeException
{
    /**
     * Služba je nedostupná, neodpověděla včas nebo ohlásila dočasnou chybu.
     */
    public const int CODE_TEMPORARY = -1;

    /**
     * Podpis potvrzení se nepodařilo ověřit, takže jeho POK nelze věřit.
     */
    public const int CODE_INVALID_SIGNATURE = -900;

    /**
     * @param  int|null  $errorCode  Kód chyby EET, nebo null, když se zprávu nepodařilo ani sestavit.
     * @param  string|null  $messageUuid  Identifikátor zprávy, která selhala, pokud byla sestavena.
     */
    public function __construct(
        string $message,
        public readonly ?int $errorCode = null,
        public readonly ?string $requestXml = null,
        public readonly ?string $responseXml = null,
        public readonly ?string $messageUuid = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $errorCode ?? 0, $previous);
    }

    /**
     * Zda se má stejná tržba poslat později znovu, místo aby se opravovala.
     */
    public function isTemporary(): bool
    {
        return in_array($this->errorCode, [self::CODE_TEMPORARY, self::CODE_INVALID_SIGNATURE], true);
    }
}
