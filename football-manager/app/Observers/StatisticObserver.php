<?php

namespace App\Observers;

use App\Models\Statistic;

class StatisticObserver
{
    public function saved(Statistic $statistic)
    {
        $player = $statistic->player;
        if (!$player) return;

        $newRating = (int) round(
            ($statistic->attacking + $statistic->defending + $statistic->stamina +
                $statistic->technical_skills + $statistic->speed + $statistic->tactical_sense) / 6
        );

        if ($player->rating !== $newRating) {
            $player->rating = $newRating;
            $player->saveQuietly();
        }
    }
}
