<?php

namespace App\Helpers;

use App\Http\Enums\PlayerPosition;

class StatsHelper
{
    public static function getStatsForPosition(PlayerPosition $position): array
    {
        return match ($position) {
            PlayerPosition::GOALKEEPER => [
                'attacking'        => rand(10, 25),
                'defending'        => rand(45, 65),
                'stamina'          => rand(50, 65),
                'technical_skills' => rand(25, 35),
                'speed'            => rand(25, 30),
                'tactical_sense'   => rand(30, 40),
            ],
            PlayerPosition::CENTRE_BACK => [
                'attacking'        => rand(15, 25),
                'defending'        => rand(45, 65),
                'stamina'          => rand(50, 65),
                'technical_skills' => rand(50, 65),
                'speed'            => rand(40, 55),
                'tactical_sense'   => rand(50, 65),
            ],
            PlayerPosition::FULLBACK => [
                'attacking'        => rand(20, 35),
                'defending'        => rand(45, 55),
                'stamina'          => rand(50, 65),
                'technical_skills' => rand(50, 65),
                'speed'            => rand(40, 60),
                'tactical_sense'   => rand(50, 65),
            ],
            PlayerPosition::MIDFIELDER => [
                'attacking'        => rand(30, 40),
                'defending'        => rand(30, 40),
                'stamina'          => rand(50, 65),
                'technical_skills' => rand(50, 65),
                'speed'            => rand(35, 45),
                'tactical_sense'   => rand(50, 65),
            ],
            PlayerPosition::WINGER => [
                'attacking'        => rand(45, 55),
                'defending'        => rand(20, 35),
                'stamina'          => rand(50, 55),
                'technical_skills' => rand(55, 65),
                'speed'            => rand(55, 70),
                'tactical_sense'   => rand(40, 50),
            ],
            PlayerPosition::STRIKER => [
                'attacking'        => rand(45, 55),
                'defending'        => rand(20, 35),
                'stamina'          => rand(50, 55),
                'technical_skills' => rand(55, 65),
                'speed'            => rand(40, 50),
                'tactical_sense'   => rand(40, 50),
            ],
            default => [
                'attacking'        => 50,
                'defending'        => 50,
                'stamina'          => 50,
                'technical_skills' => 50,
                'speed'            => 50,
                'tactical_sense'   => 50,
            ],
        };
    }
}
