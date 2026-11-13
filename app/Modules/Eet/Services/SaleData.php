<?php

namespace App\Modules\Eet\Services;

use DateTimeInterface;

final readonly class SaleData
{
    /**
     * @param  string  $taxpayerEic  EIČ poplatníka, pod kterým tržba vznikla.
     * @param  int  $unitId  Označení evidenční jednotky (provozovny).
     * @param  string  $registerId  Označení pokladny.
     * @param  string  $receiptNumber  Pořadové číslo účtenky, 1–25 znaků.
     * @param  string  $totalAmount  Částka v Kč jako desetinný řetězec, např. "1500.00".
     */
    public function __construct(
        public string $taxpayerEic,
        public int $unitId,
        public string $registerId,
        public string $receiptNumber,
        public DateTimeInterface $soldAt,
        public string $totalAmount,
    ) {}
}
