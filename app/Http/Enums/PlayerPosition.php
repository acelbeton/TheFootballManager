<?php

namespace App\Http\Enums;

enum PlayerPosition: string
{
    case GOALKEEPER = 'GOALKEEPER';
    case CENTRE_BACK = 'CENTRE_BACK';
    case FULLBACK = 'FULLBACK';
    case MIDFIELDER = 'MIDFIELDER';
    case WINGER = 'WINGER';
    case STRIKER = 'STRIKER';

    public static function getName($name): string
    {
        return match ($name) {
            self::GOALKEEPER->name => 'Goalkeeper',
            self::CENTRE_BACK->name => 'Centre Back',
            self::FULLBACK->name => 'Fullback',
            self::MIDFIELDER->name => 'Midfielder',
            self::WINGER->name => 'Winger',
            self::STRIKER->name => 'Striker',
        };
    }

    public static function abbreviation($name): string
    {
        return match ($name) {
            self::GOALKEEPER->name => 'GK',
            self::CENTRE_BACK->name => 'CB',
            self::FULLBACK->name => 'FB',
            self::MIDFIELDER->name => 'MD',
            self::WINGER->name => 'FW',
            self::STRIKER->name => 'ST',
        };
    }
}
