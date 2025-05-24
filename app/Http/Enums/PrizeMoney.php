<?php

namespace App\Http\Enums;

enum PrizeMoney: int
{
    case PRIZE_MONEY_FIRST = 100000;
    case PRIZE_MONEY_SECOND = 75000;
    case PRIZE_MONEY_THIRD = 50000;
    case PRIZE_MONEY_OTHER = 30000;
}
