<?php

namespace App\Modules\Eet\Enums;

enum StavTrzby: string
{
    case Ceka = 'ceka';
    case Potvrzena = 'potvrzena';
    case KOpakovani = 'k_opakovani';
    case Zamitnuta = 'zamitnuta';

    /**
     * Get the name shown to users.
     */
    public function label(): string
    {
        return match ($this) {
            self::Ceka => 'Čeká na odeslání',
            self::Potvrzena => 'Potvrzena',
            self::KOpakovani => 'K opakování',
            self::Zamitnuta => 'Zamítnuta',
        };
    }

    /**
     * Get the Flux badge color for the state.
     */
    public function color(): string
    {
        return match ($this) {
            self::Ceka => 'zinc',
            self::Potvrzena => 'green',
            self::KOpakovani => 'yellow',
            self::Zamitnuta => 'red',
        };
    }
}
