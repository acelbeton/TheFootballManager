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
}
