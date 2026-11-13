<?php

namespace App\Modules\Eet\Services;

use DateTimeInterface;

final readonly class UdajeTrzby
{
    /**
     * @param  string  $poradCis  Receipt number, 1–25 characters.
     * @param  string  $celkTrzba  Amount in CZK as a decimal string, e.g. "1500.00".
     */
    public function __construct(
        public string $poradCis,
        public DateTimeInterface $datTrzby,
        public string $celkTrzba,
    ) {}
}
