<?php

namespace App\Modules\Eet\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';

    /**
     * Vrátí název zobrazovaný uživatelům.
     */
    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Hotově',
            self::Card => 'Kartou',
        };
    }
}
