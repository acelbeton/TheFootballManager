<?php

namespace App\Services;

use App\Jobs\SimulateAIMatchJob;
use App\Models\MatchModel;
use App\Models\Player;
use App\Models\PlayerPerformance;
use App\Models\Standing;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class MatchSimulator
{
    public function simulatePendingMatches(?Carbon $beforeDate = null): array
    {
        $beforeDate = $beforeDate ?: now();

        $pendingMatches = MatchModel::where('match_date', '<', $beforeDate)
            ->where(function($query) {
                $query->where('home_team_score', 0)
                    ->where('away_team_score', 0);
            })
            ->get();

        $simulatedMatches = [];

        foreach ($pendingMatches as $match) {
            $simulatedMatches[] = $this->simulateMatch($match);
        }

        return $simulatedMatches;
    }

    /**
     * @throws Throwable
     */
    public function simulateMatch(MatchModel $match): MatchModel
    {
        $homeTeam = Team::findOrFail($match->home_team_id);
        $awayTeam = Team::findorFail($match->away_team_id);

        list($homeScore, $awayScore, $events) =  $this->calculateMatchResult($homeTeam, $awayTeam);

        DB::transaction(function() use ($match, $homeTeam, $awayTeam, $homeScore, $awayScore, $events) {
            $match->update([
                'home_team_score' => $homeScore,
                'away_team_score' => $awayScore,
            ]);

            $this->recordPlayerPerformances($match, $homeTeam, $awayTeam, $events);

            $this->updateStandings($match);

            $this->updatePlayerConditions($homeTeam, $awayTeam);
        });

        return $match;
    }

    public function calculateMatchResult(Team $homeTeam, Team $awayTeam): array
    {
        $homeStrength = $this->calculateTeamStrengthByPosition($homeTeam);
        $awayStrength = $this->calculateTeamStrengthByPosition($awayTeam);

        // otthoni előny
        $homeStrength['overall'] *= 1.1;

        $homeStrength = $this->applyTacticalAdjustment($homeStrength, $homeTeam->current_tactic, $awayTeam->current_tactic);
        $awayStrength = $this->applyTacticalAdjustment($awayStrength, $awayTeam->current_tactic, $homeTeam->current_tactic);

        $expectedHomeGoals = $this->calculateExpectedGoals($homeStrength['attack'], $awayStrength['defense']);
        $expectedAwayGoals = $this->calculateExpectedGoals($awayStrength['attack'], $homeStrength['defense']);

        $homeScore = $this->simulateGoalsScored($expectedHomeGoals);
        $awayScore = $this->simulateGoalsScored($expectedAwayGoals);

        $events = $this->generateMatchEvents($homeTeam, $awayTeam, $homeScore, $awayScore, $homeStrength, $awayStrength);

        return [$homeScore, $awayScore, $events];
    }

    private function calculateTeamStrengthByPosition(Team $team): array
    {
        $attack = 0;
        $defense = 0;
        $midfield = 0;
        $goalkeeper = 0;

        $attackCount = 0;
        $defenseCount = 0;
        $midfieldCount = 0;
        $goalkeeperCount = 0;

        foreach($team->players as $player) {
            if ($player->is_injured) {
                continue;
            }

            $stats = $player->statistic;
            if (!$stats) {
                continue;
            }

            $conditionFactor = $player->condition / 100;

            switch ($player->position) {
                case 'STRIKER':
                case 'WINGER':
                    $attackerRating = (
                        ($stats->attacking * 0.5) +
                        ($stats->technical_skills * 0.3) +
                        ($stats->speed * 0.1) +
                        ($stats->tactical_sense * 0.1)
                    ) * $conditionFactor;

                    $attack += $attackerRating;
                    $attackCount++;
                    break;

                case 'MIDFIELDER':
                    $midfielderRating = (
                            ($stats->technical_skills * 0.3) +
                            ($stats->tactical_sense * 0.3) +
                            ($stats->attacking * 0.2) +
                            ($stats->defending * 0.1) +
                            ($stats->stamina * 0.1)
                    ) * $conditionFactor;

                    $midfield += $midfielderRating;
                    $midfieldCount++;

                    $attack += $midfielderRating * 0.3;
                    $attackCount += 0.3;
                    $defense += $midfielderRating * 0.3;
                    $defenseCount += 0.3;
                    break;

                case 'FULLBACK':
                case 'CENTRE_BACK':
                    $defenderRating = (
                            ($stats->defending * 0.5) +
                            ($stats->tactical_sense * 0.2) +
                            ($stats->stamina * 0.1) +
                            ($stats->technical_skills * 0.1) +
                            ($stats->speed * 0.1)
                    ) * $conditionFactor;

                    $defense += $defenderRating;
                    $defenseCount++;
                    break;

                case 'GOALKEEPER':
                    $goalkeeperRating = (
                        ($stats->defending * 0.7) +
                        ($stats->tactical_sense * 0.2) +
                        ($stats->technical_skills * 0.1)
                    ) * $conditionFactor;

                    $goalkeeper += $goalkeeperRating;
                    $goalkeeperCount = 1;
                    break;
            }
        }

        $attackRating = $attackCount > 0 ? $attack / $attackCount : 0;
        $defenseRating = $defenseCount > 0 ? $defense / $defenseCount : 0;
        $midfielderRating = $midfieldCount > 0 ? $midfield / $midfieldCount : 0;
        $goalkeeperRating = $goalkeeperCount > 0 ? $goalkeeper : 0;

        $totalDefense = ($defenseRating * 0.7) + ($goalkeeperRating * 0.3);

        $overallRating = ($attackRating * 0.3) + ($midfielderRating * 0.4) + ($totalDefense * 0.3);

        return [
            'attack' => $attackRating,
            'midfield' => $midfielderRating,
            'defense' => $defenseRating,
            'goalkeeper' => $goalkeeperRating,
            'total_defense' => $totalDefense,
            'overall' => $overallRating
        ];
    }

    private function applyTacticalAdjustment(array $teamStrength, string $ownTactic, string $opponentTactic): array
    {
        $modified = $teamStrength;

        switch ($ownTactic) {
            case 'ATTACK_MODE':
                $modified['attack'] *= 1.2;
                $modified['defense'] *= 0.9;
                $modified['overall'] *= 1.05;

                // Gyengébb defend mode ellen
                if ($opponentTactic === 'DEFEND_MODE') {
                    $modified['attack'] *= 0.9;
                }
                break;

            case 'DEFEND_MODE':
                $modified['attack'] *= 0.85;
                $modified['defense'] *= 1.25;
                $modified['overall'] *= 1.0;

                // Erősebb attack mode ellen
                if ($opponentTactic === 'ATTACK_MODE') {
                    $modified['defense'] *= 1.1;
                }
                break;

            case 'DEFAULT_MODE':
                // balanced
                $modified['attack'] *= 1.05;
                $modified['defense'] *= 1.05;
                $modified['overall'] *= 1.05;
                break;
        }

        return $modified;
    }

    private function calculateExpectedGoals(float $attackRating, float $defenseRating): float
    {
        $baseGoals = $attackRating / 25;
        $defenseFactor = max(0.3, min(0.9, 1 - ($defenseRating / 150)));
        $expectedGoals = $baseGoals * $defenseFactor;
        $randomFactor = 0.8 + (mt_rand() / mt_getrandmax() * 0.4);

        return $expectedGoals * $randomFactor;
    }

    private function simulateGoalsScored(float $expectedGoals): int
    {
        $lambda = $expectedGoals;
        $L = exp(-$lambda);
        $k = 0;
        $p = 1.0;

        do {
            $k++;
            $p *= mt_rand() / mt_getrandmax();
        } while ($p > $L);

        return max(0, $k - 1);
    }

    private function generateMatchEvents(Team $homeTeam, Team $awayTeam, int $homeScore, int $awayScore, array $homeStrength, array $awayStrength): array
    {
        $events = [
            'home_goals' => [],
            'away_goals' => [],
            'yellow_cards' => [],
            'red_cards' => [],
            'injuries' => [],
            'player_ratings' => []
        ];

        $homeAttackers = $this->getOffensivePlayers($homeTeam);

        for ($i = 0; $i < $homeScore; $i++) {
            $minute = $this->generateRealisticMinute($i, $homeScore);
            $scorer = $this->selectPlayerByWeightedStats($homeAttackers, ['attacking', 'technical_skills']);
            $assister = $this->selectPlayerByWeightedStats($homeAttackers->where('id', '!=', $scorer->getKey()), ['technical_skills', 'tactical_sense']);

            $events['home_goals'][] = [
                'minute' => $minute,
                'scorer_id' => $scorer->getKey(),
                'scorer_name' => $scorer->name,
                'assister_id' => $assister ? $assister->getKey() : null,
                'assister_name' => $assister ? $assister->name : null,
            ];
        }

        $awayAttackers = $this->getOffensivePlayers($awayTeam);
        for ($i = 0; $i < $awayScore; $i++) {
            $minute = $this->generateRealisticMinute($i, $awayScore);
            $scorer = $this->selectPlayerByWeightedStats($awayAttackers, ['attacking', 'technical_skills']);
            $assister = $this->selectPlayerByWeightedStats($awayAttackers->where('id', '!=', $scorer->getKey()), ['technical_skills', 'tactical_sense']);

            $events['away_goals'][] = [
                'minute' => $minute,
                'scorer_id' => $scorer->getKey(),
                'scorer_name' => $scorer->name,
                'assister_id' => $assister ? $assister->getKey() : null,
                'assister_name' => $assister ? $assister->name : null,
            ];
        }

        // Sárga lapok (1-5 meccsenként)
        $numYellowCards = min(10, max(0, $this->getRandomNormal(3, 1.5)));
        $this->generateCards($events['yellow_cards'], $homeTeam, $awayTeam, $numYellowCards);

        // Piros lapok (0-1 meccsenként)
        $redCardChance = 15;
        if (mt_rand(1, 100) <= $redCardChance) {
            $this->generateCards($events['red_cards'], $homeTeam, $awayTeam, 1);
        }

        $injuryChance = 10; // 10% sérülés esély
        if (mt_rand(1, 100) <= $injuryChance) {
            $this->generateInjuries($events['injuries'], $homeTeam, $awayTeam);
        }

        $this->calculatePlayerRatings($events['player_ratings'], $homeTeam, $awayTeam, $events);

        usort($events['home_goals'], function($a, $b) {
            return $a['minute'] <=> $b['minute'];
        });

        usort($events['away_goals'], function($a, $b) {
            return $a['minute'] <=> $b['minute'];
        });

        usort($events['yellow_cards'], function($a, $b) {
            return $a['minute'] <=> $b['minute'];
        });

        usort($events['red_cards'], function($a, $b) {
            return $a['minute'] <=> $b['minute'];
        });

        return $events;
    }

    private function generateRealisticMinute(int $goalNumber, int $totalGoals): int
    {
        $baseMinute = mt_rand(1, 90);

        if (mt_rand(1, 100) <= 60) {
            $baseMinute = mt_rand(46, 90);

            if (mt_rand(1, 100) <= 40) {
                $baseMinute = mt_rand(75, 90);

                if (mt_rand(1, 100) <= 15) {
                    $baseMinute = mt_rand(90, 94);
                }
            }
        }

        if ($totalGoals > 1) {
            $avgSpacing = 90 / ($totalGoals + 1);
            $targetMinute = ($goalNumber + 1) * $avgSpacing;

            $blendFactor = 0.7;
            $minute = (int)(($baseMinute * (1 - $blendFactor)) + ($targetMinute * $blendFactor));

            return max(1, min(94, $minute));
        }

        return $baseMinute;
    }

    private function generateCards(array &$cards, Team $homeTeam, Team $awayTeam, int $count): void
    {
        $homePlayers = $homeTeam->players->where('position', '!=', 'GOALKEEPER')
            ->where('is_injured', false);

        $awayPlayers = $awayTeam->players->where('position', '!=', 'GOALKEEPER')
            ->where('is_injured', false);

        $homePlayerWeights = [];
        foreach ($homePlayers as $player) {
            $weight = 1.0;

            if (in_array($player->position, ['CENTRE_BACK', 'FULLBACK'])) {
                $weight *= 2.0;
            } elseif ($player->position === 'MIDFIELDER') {
                $weight *= 1.5;
            }

            $stats = $player->statistics;
            if ($stats) {
                $weight *= (100 - $stats->technical_skills) / 50;
            }

            $homePlayerWeights[$player->getKey()] = max(0.5, min(3.0, $weight));
        }

        $awayPlayerWeights = [];
        foreach ($awayPlayers as $player) {
            $weight = 1.0;

            if (in_array($player->position, ['CENTRE_BACK', 'FULLBACK'])) {
                $weight *= 2.0;
            } elseif ($player->position === 'MIDFIELDER') {
                $weight *= 1.5;
            }

            $stats = $player->statistics;
            if ($stats) {
                $weight *= (100 - $stats->technical_skills) / 50;
            }

            $awayPlayerWeights[$player->getKey()] = max(0.5, min(3.0, $weight));
        }

        for ($i = 0; $i < $count; $i++) {
            $isHome = (mt_rand(1, 100) <= 50);

            if ($isHome && !$homePlayers->isEmpty()) {
                $player = $this->selectPlayerByWeights($homePlayers, $homePlayerWeights);
                $minute = mt_rand(20, 90);

                $cards[] = [
                    'minute' => $minute,
                    'team' => 'home',
                    'player_id' => $player->getKey(),
                    'player_name' => $player->name,
                ];
            } elseif (!$awayPlayers->isEmpty()) {
                $player = $this->selectPlayerByWeights($awayPlayers, $awayPlayerWeights);
                $minute = mt_rand(20, 90);

                $cards[] = [
                    'minute' => $minute,
                    'team' => 'away',
                    'player_id' => $player->getKey(),
                    'player_name' => $player->name,
                ];
            }
        }
    }

    private function generateInjuries(array &$injuries, Team $homeTeam, Team $awayTeam): void
    {
        $isHome = (mt_rand(1, 100) <= 50);

        $players = $isHome ?
            $homeTeam->players->where('is_injured', false) :
            $awayTeam->players->where('is_injured', false);

        if ($players->isEmpty()) {
            return;
        }

        $playerWeights = [];
        foreach ($players as $player) {
            $weight = 1.0;

            $stats = $player->statistics;
            if ($stats) {
                $weight *= (100 - $stats->stamina) / 50;

                $weight *= (100 - $player->condition) / 50;
            }

            $playerWeights[$player->id] = max(0.5, min(3.0, $weight));
        }

        $player = $this->selectPlayerByWeights($players, $playerWeights);
        $minute = mt_rand(1, 90);

        $player->is_injured = true;
        $player->save();

        $injuries[] = [
            'minute' => $minute,
            'team' => $isHome ? 'home' : 'away',
            'player_id' => $player->id,
            'player_name' => $player->name,
        ];
    }

    private function calculatePlayerRatings(array &$ratings, Team $homeTeam, Team $awayTeam, array $events): void
    {
        $playerRatings = [];

        foreach ($homeTeam->players as $player) {
            if ($player->is_injured) continue;

            $playerRatings[$player->getKey()] = [
                'player_id' => $player->getKey(),
                'player_name' => $player->name,
                'team' => 'home',
                'goals' => 0,
                'assists' => 0,
                'base_rating' => 6.0,
            ];
        }

        foreach ($awayTeam->players as $player) {
            if ($player->is_injured) continue;

            $playerRatings[$player->getKey()] = [
                'player_id' => $player->getKey(),
                'player_name' => $player->name,
                'team' => 'away',
                'goals' => 0,
                'assists' => 0,
                'base_rating' => 6.0,
            ];
        }

        foreach ($events['home_goals'] as $goal) {
            if (isset($playerRatings[$goal['scorer_id']])) {
                $playerRatings[$goal['scorer_id']]['goals']++;
                $playerRatings[$goal['scorer_id']]['base_rating'] += 0.8;
            }

            if ($goal['assister_id'] && isset($playerRatings[$goal['assister_id']])) {
                $playerRatings[$goal['assister_id']]['assists']++;
                $playerRatings[$goal['assister_id']]['base_rating'] += 0.5;
            }
        }

        foreach ($events['away_goals'] as $goal) {
            if (isset($playerRatings[$goal['scorer_id']])) {
                $playerRatings[$goal['scorer_id']]['goals']++;
                $playerRatings[$goal['scorer_id']]['base_rating'] += 0.8;
            }

            if ($goal['assister_id'] && isset($playerRatings[$goal['assister_id']])) {
                $playerRatings[$goal['assister_id']]['assists']++;
                $playerRatings[$goal['assister_id']]['base_rating'] += 0.5;
            }
        }

        foreach ($events['yellow_cards'] as $card) {
            if (isset($playerRatings[$card['player_id']])) {
                $playerRatings[$card['player_id']]['base_rating'] -= 0.3;
            }
        }

        foreach ($events['red_cards'] as $card) {
            if (isset($playerRatings[$card['player_id']])) {
                $playerRatings[$card['player_id']]['base_rating'] -= 1.5;
            }
        }

        $homeBonus = 0;
        $awayBonus = 0;

        if ($events['home_goals'] > $events['away_goals']) {
            $homeBonus = 0.3;
            $awayBonus = -0.1;
        } elseif ($events['home_goals'] < $events['away_goals']) {
            $homeBonus = -0.1;
            $awayBonus = 0.3;
        } else {
            $homeBonus = $awayBonus = 0.1;
        }

        foreach ($playerRatings as $playerId => $data) {
            $bonus = ($data['team'] === 'home') ? $homeBonus : $awayBonus;
            $finalRating = min(10.0, max(1.0, $data['base_rating'] + $bonus));

            $playerRatings[$playerId]['rating'] = round($finalRating * 10);
            $ratings[$playerId] = $playerRatings[$playerId];
        }
    }

    public function recordPlayerPerformances(MatchModel $match, Team $homeTeam, Team $awayTeam, array $events): void
    {
        $playerRatings = $events['player_ratings'];

        foreach ($playerRatings as $playerId => $data) {
            $playerPerformance = new PlayerPerformance([
                'player_id' => $playerId,
                'match_id' => $match->getKey(),
                'goals_scored' => $data['goals'] ?? 0,
                'assists' => $data['assists'] ?? 0,
                'rating' => $data['rating'] ?? 60,
                'minutes_played' => 90,
            ]);

            $playerPerformance->save();
        }
    }

    private function updateStandings(MatchModel $match): void
    {
        $homeTeam = Team::findOrFail($match->home_team_id);
        $awayTeam = Team::findOrFail($match->away_team_id);

        $homeStanding = Standing::firstOrCreate([
            'season_id' => $homeTeam->season_id,
            'team_id' => $homeTeam->getKey(),
        ]);

        $awayStanding = Standing::firstOrCreate([
            'season_id' => $awayTeam->season_id,
            'team_id' => $awayTeam->getKey(),
        ]);

        $homeStanding->matches_played += 1;
        $awayStanding->matches_played += 1;

        $homeStanding->goals_scored += $match->home_team_score;
        $homeStanding->goals_conceded += $match->away_team_score;
        $awayStanding->goals_scored += $match->away_team_score;
        $awayStanding->goals_conceded += $match->home_team_score;

        if ($match->home_team_score > $match->away_team_score) {
            $homeStanding->matches_won += 1;
            $homeStanding->points += 3;
            $awayStanding->matches_lost += 1;
        } elseif ($match->home_team_score < $match->away_team_score) {
            $awayStanding->matches_won += 1;
            $awayStanding->points += 3;
            $homeStanding->matches_lost += 1;
        } else {
            $homeStanding->matches_drawn += 1;
            $awayStanding->matches_drawn += 1;
            $homeStanding->points += 1;
            $awayStanding->points += 1;
        }

        if ($homeStanding->matches_played > 0) {
            $homeStanding->points_per_week_avg = $homeStanding->points / $homeStanding->matches_played;
        }
        if ($awayStanding->matches_played > 0) {
            $awayStanding->points_per_week_avg = $awayStanding->points / $awayStanding->matches_played;
        }

        $homeStanding->save();
        $awayStanding->save();
    }

    private function updatePlayerConditions(Team $homeTeam, Team $awayTeam): void
    {
        $homePlayers = Player::where('team_id', $homeTeam->getKey())->get();
        $awayPlayers = Player::where('team_id', $awayTeam->getKey())->get();

        foreach ($homePlayers as $player) {
            if ($player->is_injured) continue;

            $stats = $player->statistics;
            $staminaFactor = $stats ? (100 - $stats->stamina) / 100 : 0.5;

            $conditionDrop = 5 + (int)(10 * $staminaFactor);
            $player->condition = max(10, $player->condition - $conditionDrop);

            $injuryChance = 5 + (int)((100 - $player->condition) / 10);
            if (rand(1, 100) <= $injuryChance) {
                $player->is_injured = true;
            }

            $player->save();
        }

        foreach ($awayPlayers as $player) {
            if ($player->is_injured) continue;

            $stats = $player->statistics;
            $staminaFactor = $stats ? (100 - $stats->stamina) / 100 : 0.5;

            $conditionDrop = 5 + (int)(10 * $staminaFactor);
            $player->condition = max(10, $player->condition - $conditionDrop);

            $injuryChance = 5 + (int)((100 - $player->condition) / 10);
            if (rand(1, 100) <= $injuryChance) {
                $player->is_injured = true;
            }

            $player->save();
        }
    }

    private function getOffensivePlayers(Team $team)
    {
        return $team->players
            ->where('is_injured', false)
            ->whereIn('position', ['STRIKER', 'WINGER', 'MIDFIELDER']);
    }

    private function selectPlayerByWeightedStats(Collection $players, array $statWeights, array $excludeIds = [])
    {
        if ($players->isEmpty()) {
            return null;
        }

        $players = $players->whereNotIn('id', $excludeIds);
        if ($players->isEmpty()) {
            return null;
        }

        $playerRatings = [];

        foreach ($players as $player) {
            $stats = $player->statistics;
            if (!$stats) continue;

            $weightedRating = 0;
            $totalWeight = 0;

            foreach ($statWeights as $stat) {
                $weight = 1.0;
                $weightedRating += ($stats->$stat ?? 50) * $weight;
                $totalWeight += $weight;
            }

            if ($totalWeight > 0) {
                $weightedRating /= $totalWeight;
            }

            $conditionFactor = $player->condition / 100;
            $weightedRating *= $conditionFactor;

            $playerRatings[$player->getKey()] = $weightedRating;
        }

        return $this->selectPlayerByWeights($players, $playerRatings);
    }

    private function selectPlayerByWeights(Collection $players, array $weights)
    {
        $totalWeight = array_sum($weights);
        if ($totalWeight <= 0) {
            return $players->random();
        }

        $randValue = mt_rand(0, (int)($totalWeight * 100)) / 100;
        $cumulativeWeight = 0;

        foreach ($weights as $playerId => $weight) {
            $cumulativeWeight += $weight;
            if ($cumulativeWeight >= $randValue) {
                return $players->firstWhere('id', $playerId);
            }
        }

        return $players->first();
    }

    private function getRandomNormal(float $mean, float $standardDeviation): float
    {
        $x = mt_rand() / mt_getrandmax();
        $y = mt_rand() / mt_getrandmax();

        return sqrt(-2 * log($x)) * cos(2 * M_PI * $y) * $standardDeviation + $mean;
    }

    public function queueMatch(MatchModel $match): bool
    {
        if ($match->home_team_score > 0 || $match->away_team_score > 0) {
            return false;
        }

        SimulateAIMatchJob::dispatch($match->getKey())->onQueue('ai-match-simulation');

        return true;
    }
}
