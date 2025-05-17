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

        if ($teamCount < 2) {
            throw new Exception("Cannot generate fixtures, need at least 2 teams");
        }

        if ($teamCount % 2 != 0) {
            throw new Exception("Cannot generate fixtures, need even number of teams");
        }

        $fixtures = [];
        $teamIds = $teams->pluck('id')->toArray();

        $totalRounds = $teamCount - 1;
        $matchesPerRound = $teamCount / 2;

        $weekDistribution = $this->distributeRoundsToWeeks($totalRounds, 4);

        $startDate = Carbon::parse($season->start_date);
        if ($startDate->isPast()) {
            $startDate = now();
        }

        DB::transaction(function() use ($teamIds, $teamCount, $startDate, $weekDistribution, &$fixtures) {
            $roundRobinFixtures = $this->generateRoundRobinFixtures($teamIds);
            $currentRound = 0;

            for ($week = 0; $week < 4; $week++) {
                $roundsThisWeek = $weekDistribution[$week] ?? 0;

                if ($roundsThisWeek > 0) {
                    $weekStartDate = (clone $startDate)->addWeeks($week);
                    $weekEndDate = (clone $weekStartDate)->addDays(6);
                    $matchDates = $this->calculateMatchDatesForWeek($weekStartDate, $weekEndDate, $roundsThisWeek);

                    for ($i = 0; $i < $roundsThisWeek && $currentRound < count($roundRobinFixtures); $i++) {
                        $matchDate = $matchDates[$i];

                        foreach ($roundRobinFixtures[$currentRound] as $match) {
                            $fixtures[] = $this->createMatch($match[0], $match[1], $matchDate);
                        }

                        $currentRound++;
                    }
                }
            }
        });

        return $fixtures;
    }

    private function calculateMatchDatesForWeek(Carbon $weekStart, Carbon $weekEnd, int $matchCount): array
    {
        $matchDates = [];

        if ($matchCount == 1) {
            $weekend = (clone $weekStart)->addDays(rand(5, 6)); // Saturday or Sunday
            $matchHour = rand(12, 20);
            $matchDates[] = $weekend->setHour($matchHour)->setMinute(0)->setSecond(0);
            return $matchDates;
        }

        $daysBetweenMatches = max(1, intval(7 / $matchCount));

        $currentDate = clone $weekStart;
        for ($i = 0; $i < $matchCount; $i++) {
            $matchHour = rand(12, 20);
            $matchDate = (clone $currentDate)->setHour($matchHour)->setMinute(0)->setSecond(0);
            $matchDates[] = $matchDate;
            $currentDate = $currentDate->addDays($daysBetweenMatches);

            if ($currentDate->gt($weekEnd)) {
                $currentDate = clone $weekEnd;
            }
        }

        return $matchDates;
    }

    private function distributeRoundsToWeeks(int $totalRounds, int $totalWeeks): array
    {
        $distribution = array_fill(0, $totalWeeks, 0);

        for ($i = 0; $i < $totalRounds; $i++) {
            $week = $i % $totalWeeks;
            $distribution[$week]++;
        }

        return $distribution;
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
