<?php

namespace App\Http\Enums;

enum PrizeMoney: int
{
    case PRIZE_MONEY_FIRST = 1000;
    case PRIZE_MONEY_SECOND = 750;
    case PRIZE_MONEY_THIRD = 500;
    case PRIZE_MONEY_OTHER = 300;
}
