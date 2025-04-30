<?php

namespace App\Services;

use App\Models\Player;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class TrainingService
{
    /*
     * Team training-ben választunk kettő player statot, amelyet 0, 1 vagy 2-vel megemelünk,
     * max 4-5 playernél. Condition-t random csökkentjük minenkinél 2-8 között
     */
    public static function trainTeam()
    {
        $team = Auth::user()->currentTeam;
        $players = $team->players->load('statistics');

        self::getEach($players, 'individual');
    }

    public static function trainPlayer(array $selectedPlayers)
    {
        $players = Player::whereIn('id', $selectedPlayers)->get();

        self::getEach($players, 'player');
    }

    /**
     * @param $players
     * @return void
     */
    protected static function getEach($players, $trainingMode): void
    {
        $players->each(function ($player) use ($trainingMode) {
            $selectableStatistics = [
                'attacking',
                'defending',
                'stamina',
                'technical_skills',
                'speed',
                'tactical_sense'
            ];

            // 25%-os esély
            if (rand(1, 100) <= ($trainingMode === 'player' ? 80 : 25)) {
                $min = $trainingMode === 'player' ? 2 : 1;
                $max = $trainingMode === 'player' ? 3 : 2;
                $randomKeys = Arr::random($selectableStatistics, rand(1, $max));

                foreach ($randomKeys as $key) {
                    $increase = rand($min, $max);
                    $player->statistics->$key += $increase;
                }
                $player->statistics->save();
            }

            // 90%-os esély
            if (rand(1, 100) <= 90) {
                $chance = rand(1, 100);
                $increase = $chance <= 75 ? rand(2, 5) : rand(5, 8); // kisebb az esély a nagyobb számra
                $player->condition -= $increase;
                $player->save();
            }
        });
    }
}
