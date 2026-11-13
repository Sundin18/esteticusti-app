<?php

namespace App\Modules\Eet\Enums;

enum SaleStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case AwaitingRetry = 'awaiting_retry';
    case Rejected = 'rejected';

    /**
     * Vrátí název zobrazovaný uživatelům.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Čeká na odeslání',
            self::Confirmed => 'Potvrzena',
            self::AwaitingRetry => 'K opakování',
            self::Rejected => 'Zamítnuta',
        };
    }

    /**
     * Vrátí barvu štítku Flux pro daný stav.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'zinc',
            self::Confirmed => 'green',
            self::AwaitingRetry => 'yellow',
            self::Rejected => 'red',
        };
    }
}
