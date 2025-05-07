<?php

namespace App\Services;

use App\Models\MatchModel;
use App\Models\Season;
use App\Models\Team;
use Carbon\Carbon;
use DB;
use Exception;
use Throwable;

class MatchSchedulerService
{
    /**
     * @throws Exception|Throwable
     */
    public function generateFixturesForSeason(Season $season): array
    {
        $teams = Team::where('season_id', $season->getKey())->get();
        $teamCount = $teams->count();

        if ($teamCount % 2 != 0) {
            throw new Exception("Cannot generate fixtures, need even number of teams");
        }

        $fixtures = [];
        $teamIds = $teams->pluck('id')->toArray();

        $roundCount = ($teamCount - 1) * 2;
        $startDate = Carbon::parse($season->start_date);
        $endDate = Carbon::parse($season->end_date);

        $matchDates = $this->calculateMatchDates($startDate, $endDate, $roundCount);

        DB::transaction(function() use ($teamIds, $teamCount, $roundCount, $matchDates, &$fixtures) {
            $firstHalfFixtures = $this->generateRoundRobinFixtures($teamIds);

            for ($round = 0; $round < ($teamCount - 1); $round++) {
                $matchDate = $matchDates[$round];
                foreach ($firstHalfFixtures[$round] as $match) {
                    $fixtures[] = $this->createMatch($match[0], $match[1], $matchDate);
                }
            }

            for ($round = 0; $round < ($teamCount - 1); $round++) {
                $matchDate = $matchDates[$round + $teamCount - 1];
                foreach ($firstHalfFixtures[$round] as $match) {
                    $fixtures[] = $this->createMatch($match[1], $match[0], $matchDate);
                }
            }
        });

        return $fixtures;
    }

    private function generateRoundRobinFixtures(array $teamIds): array
    {
        $teamCount = count($teamIds);
        $rounds = $teamCount - 1;
        $matchesPerRound = $teamCount / 2;

        $teams = $teamIds;

        $fixedTeam = array_shift($teams);

        $fixtures = [];

        for ($round = 0; $round < $rounds; $round++) {
            $roundFixtures = [];

            $roundFixtures[] = [$fixedTeam, $teams[0]];

            for ($match = 1; $match < $matchesPerRound; $match++) {
                $roundFixtures[] = [$teams[$match], $teams[$teamCount - $match - 1]];
            }

            $fixtures[] = $roundFixtures;

            array_unshift($teams, array_pop($teams));
        }

        return $fixtures;
    }

    private function createMatch(int $homeTeamId, int $awayTeamId, Carbon $matchDate): MatchModel
    {
        return MatchModel::create([
            'home_team_id' => $homeTeamId,
            'away_team_id' => $awayTeamId,
            'home_team_score' => 0,
            'away_team_score' => 0,
            'match_date' => $matchDate,
        ]);
    }

    public function calculateMatchDates(Carbon $startDate, Carbon $endDate, int $roundCount): array
    {
        $seasonDuration = $endDate->diffInDays($startDate);
        $daysBetweenRounds = max(1, floor($seasonDuration / $roundCount));

        $matchDates = [];
        $currentDate = clone $startDate;

        for ($i = 0; $i < $roundCount; $i++) {
            $matchHour = rand(12, 20);
            $matchDate = (clone $currentDate)->setHour($matchHour)->setMinute(0)->setSecond(0);
            $matchDates[] = $matchDate;

            $currentDate = $currentDate->addDays($daysBetweenRounds);
        }

        return $matchDates;
    }
}
