<?php

namespace App\Enums;

enum Theme: string
{
    case Swiateczna = 'swiateczna';
    case Slubna = 'slubna';
    case Urodzinowa = 'urodzinowa';

    public function label(): string
    {
        return match ($this) {
            self::Swiateczna => 'Świąteczna',
            self::Slubna => 'Ślubna',
            self::Urodzinowa => 'Urodzinowa',
        };
    }
}
