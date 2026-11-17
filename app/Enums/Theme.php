<?php

namespace App\Enums;

enum Theme: string
{
    case Swiateczna = 'swiateczna';
    case Choinkowa = 'choinkowa';
    case Mikolajowa = 'mikolajowa';
    case Zimowa = 'zimowa';
    case Slubna = 'slubna';
    case Urodzinowa = 'urodzinowa';

    public function label(): string
    {
        return match ($this) {
            self::Swiateczna => 'Święta: klasyczna',
            self::Choinkowa => 'Święta: choinka',
            self::Mikolajowa => 'Święta: Mikołaj',
            self::Zimowa => 'Święta: zima',
            self::Slubna => 'Ślub',
            self::Urodzinowa => 'Urodziny',
        };
    }
}
