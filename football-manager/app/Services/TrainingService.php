<?php

namespace App\Services;

use App\Http\Enums\TrainingType;
use App\Models\Player;
use App\Models\TrainingSession;
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

        $trainingResults = self::getEach($players, 'individual');

        $session = TrainingSession::create([
            'type' => TrainingType::TEAM,
            'user_id' => Auth::id(),
            'team_id' => $team->getKey(),
            'results' => $trainingResults,
        ]);

        return $trainingResults;
    }

    public static function trainPlayer(array $selectedPlayers)
    {
        $players = Player::whereIn('id', $selectedPlayers)->get();

        $trainingResults = self::getEach($players, 'player');

        $session = TrainingSession::create([
            'type' => TrainingType::INDIVIDUAL,
            'user_id' => Auth::id(),
            'team_id' => Auth::user()->currentTeam->getKey(),
            'participants' => $players->pluck('id')->toArray(),
            'results' => $trainingResults,
        ]);

        return $trainingResults;
    }


    protected static function getEach($players, $trainingMode): array
    {
        $results = [];

        $players->each(function ($player) use ($trainingMode, &$results) {
            $playerId = $player->getKey();
            $results[$playerId] = [
                'stats' => [],
                'condition' => 0
            ];

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
                    $results[$playerId]['stats'][$key] = $increase;
                }
                $player->statistics->save();
            }

            // 90%-os esély
            if (rand(1, 100) <= 90) {
                $chance = rand(1, 100);
                $increase = $chance <= 75 ? rand(2, 5) : rand(5, 8); // kisebb az esély a nagyobb számra
                $player->condition -= $increase;
                $results[$playerId]['condition'] = -$increase;

                $player->save();
            }
        });

        return $results;
    }
}
