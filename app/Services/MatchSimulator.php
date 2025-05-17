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
    /**
     * @throws Throwable
     */
    public function simulateMatch(MatchModel $match): MatchModel
    {
        $homeTeam = Team::findOrFail($match->home_team_id);
        $awayTeam = Team::findorFail($match->away_team_id);

        list($homeScore, $awayScore, $events) = $this->calculateMatchResult($homeTeam, $awayTeam);

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
        $homeAttack = $this->calculateTeamAttack($homeTeam);
        $homeDefense = $this->calculateTeamDefense($homeTeam);
        $awayAttack = $this->calculateTeamAttack($awayTeam);
        $awayDefense = $this->calculateTeamDefense($awayTeam);
        $homeAttack *= 1.1;
        $homeDefense *= 1.05;
        $this->applyTactics($homeTeam, $awayTeam, $homeAttack, $homeDefense, $awayAttack, $awayDefense);
        $expectedHomeGoals = ($homeAttack / $awayDefense) * 1.5;
        $expectedAwayGoals = ($awayAttack / $homeDefense) * 1.2;
        $expectedHomeGoals *= (0.7 + (mt_rand() / mt_getrandmax() * 0.6));
        $expectedAwayGoals *= (0.7 + (mt_rand() / mt_getrandmax() * 0.6));
        $homeScore = $this->simulateGoalsScored($expectedHomeGoals);
        $awayScore = $this->simulateGoalsScored($expectedAwayGoals);
        $events = $this->generateMatchEvents($homeTeam, $awayTeam, $homeScore, $awayScore);

        return [$homeScore, $awayScore, $events];
    }

    private function calculateTeamAttack(Team $team): float
    {
        $totalAttack = 0;
        $count = 0;

        foreach ($team->players as $player) {
            if ($player->is_injured) continue;

            $stats = $player->statistic;
            if (!$stats) continue;

            $contribution = 0;
            $conditionFactor = $player->condition / 100;

            switch ($player->position) {
                case 'STRIKER':
                    $contribution = $stats->attacking * 1.0 * $conditionFactor;
                    $totalAttack += $contribution;
                    $count += 1;
                    break;

                case 'WINGER':
                    $contribution = $stats->attacking * 0.8 * $conditionFactor;
                    $totalAttack += $contribution;
                    $count += 0.8;
                    break;

                case 'MIDFIELDER':
                    $contribution = $stats->attacking * 0.5 * $conditionFactor;
                    $totalAttack += $contribution;
                    $count += 0.5;
                    break;
            }
        }

        return $count > 0 ? $totalAttack / $count : 50;
    }

    private function calculateTeamDefense(Team $team): float
    {
        $totalDefense = 0;
        $count = 0;

        foreach ($team->players as $player) {
            if ($player->is_injured) continue;

            $stats = $player->statistic;
            if (!$stats) continue;

            $contribution = 0;
            $conditionFactor = $player->condition / 100;

            switch ($player->position) {
                case 'GOALKEEPER':
                    $contribution = $stats->defending * 1.5 * $conditionFactor;
                    $totalDefense += $contribution;
                    $count += 1.5;
                    break;

                case 'CENTRE_BACK':
                    $contribution = $stats->defending * 1.0 * $conditionFactor;
                    $totalDefense += $contribution;
                    $count += 1;
                    break;

                case 'FULLBACK':
                    $contribution = $stats->defending * 0.8 * $conditionFactor;
                    $totalDefense += $contribution;
                    $count += 0.8;
                    break;

                case 'MIDFIELDER':
                    $contribution = $stats->defending * 0.4 * $conditionFactor;
                    $totalDefense += $contribution;
                    $count += 0.4;
                    break;
            }
        }

        return $count > 0 ? $totalDefense / $count : 50;
    }

    private function applyTactics(Team $homeTeam, Team $awayTeam, float &$homeAttack, float &$homeDefense, float &$awayAttack, float &$awayDefense): void
    {
        if ($homeTeam->current_tactic === 'ATTACK_MODE') {
            $homeAttack *= 1.2;
            $homeDefense *= 0.9;
        } elseif ($homeTeam->current_tactic === 'DEFEND_MODE') {
            $homeAttack *= 0.9;
            $homeDefense *= 1.2;
        }

        if ($awayTeam->current_tactic === 'ATTACK_MODE') {
            $awayAttack *= 1.2;
            $awayDefense *= 0.9;
        } elseif ($awayTeam->current_tactic === 'DEFEND_MODE') {
            $awayAttack *= 0.9;
            $awayDefense *= 1.2;
        }

        if ($homeTeam->current_tactic === 'ATTACK_MODE' && $awayTeam->current_tactic === 'DEFEND_MODE') {
            $homeAttack *= 0.9;
        }

        if ($awayTeam->current_tactic === 'ATTACK_MODE' && $homeTeam->current_tactic === 'DEFEND_MODE') {
            $awayAttack *= 0.9;
        }
    }

    private function simulateGoalsScored(float $expectedGoals): int
    {
        $goals = 0;
        $probability = $expectedGoals / 3;

        for ($i = 0; $i < 5; $i++) {
            if (mt_rand() / mt_getrandmax() < $probability) {
                $goals++;
            }
        }

        if (mt_rand(1, 100) <= 5 && $goals > 0) {
            $goals += mt_rand(1, 2);
        }

        return $goals;
    }

    private function generateMatchEvents(Team $homeTeam, Team $awayTeam, int $homeScore, int $awayScore): array
    {
        $events = [
            'home_goals' => [],
            'away_goals' => [],
            'yellow_cards' => [],
            'red_cards' => [],
            'injuries' => [],
            'player_ratings' => []
        ];

        $this->generateGoals($events, $homeTeam, $homeScore, 'home');
        $this->generateGoals($events, $awayTeam, $awayScore, 'away');

        $totalCards = mt_rand(0, 4);
        $this->generateCards($events, $homeTeam, $awayTeam, $totalCards);

        if (mt_rand(1, 100) <= 15) {
            $this->generateInjury($events, mt_rand(0, 1) ? $homeTeam : $awayTeam);
        }

        $this->calculateSimplePlayerRatings($events, $homeTeam, $awayTeam, $homeScore, $awayScore);

        return $events;
    }

    private function generateGoals(array &$events, Team $team, int $score, string $side): void
    {
        $offensivePlayers = $team->players
            ->where('is_injured', false)
            ->whereIn('position', ['STRIKER', 'WINGER', 'MIDFIELDER']);

        if ($offensivePlayers->isEmpty() || $score <= 0) {
            return;
        }

        for ($i = 0; $i < $score; $i++) {
            $weights = [];
            foreach ($offensivePlayers as $player) {
                switch ($player->position) {
                    case 'STRIKER':
                        $weights[$player->id] = 10;
                        break;
                    case 'WINGER':
                        $weights[$player->id] = 5;
                        break;
                    case 'MIDFIELDER':
                        $weights[$player->id] = 3;
                        break;
                    default:
                        $weights[$player->id] = 1;
                }
            }

            $scorer = $this->selectPlayerByWeights($offensivePlayers, $weights);

            $assister = null;
            if (mt_rand(1, 100) <= 70) {
                $possibleAssisters = $offensivePlayers->where('id', '!=', $scorer->id);
                if (!$possibleAssisters->isEmpty()) {
                    $assister = $possibleAssisters->random();
                }
            }

            $minute = mt_rand(1, 90);
            if (mt_rand(1, 100) <= 60) {
                $minute = mt_rand(45, 90);
            }

            $events["{$side}_goals"][] = [
                'minute' => $minute,
                'scorer_id' => $scorer->id,
                'scorer_name' => $scorer->name,
                'assister_id' => $assister ? $assister->id : null,
                'assister_name' => $assister ? $assister->name : null,
            ];
        }

        usort($events["{$side}_goals"], function($a, $b) {
            return $a['minute'] <=> $b['minute'];
        });
    }

    private function generateCards(array &$events, Team $homeTeam, Team $awayTeam, int $cardCount): void
    {
        for ($i = 0; $i < $cardCount; $i++) {
            $isHomeTeam = mt_rand(0, 1) == 1;
            $team = $isHomeTeam ? $homeTeam : $awayTeam;
            $side = $isHomeTeam ? 'home' : 'away';

            $defenders = $team->players
                ->where('is_injured', false)
                ->whereIn('position', ['CENTRE_BACK', 'FULLBACK', 'MIDFIELDER']);

            if ($defenders->isEmpty()) {
                continue;
            }

            $player = $defenders->random();
            $minute = mt_rand(20, 85);

            $events['yellow_cards'][] = [
                'minute' => $minute,
                'team' => $side,
                'player_id' => $player->id,
                'player_name' => $player->name,
            ];
        }

        if (mt_rand(1, 100) <= 5) {
            if (!empty($events['yellow_cards'])) {
                $yellowCard = $events['yellow_cards'][array_rand($events['yellow_cards'])];
                $minute = min(89, $yellowCard['minute'] + mt_rand(5, 20));

                $events['red_cards'][] = [
                    'minute' => $minute,
                    'team' => $yellowCard['team'],
                    'player_id' => $yellowCard['player_id'],
                    'player_name' => $yellowCard['player_name'],
                ];
            }
        }

        usort($events['yellow_cards'], function($a, $b) {
            return $a['minute'] <=> $b['minute'];
        });

        usort($events['red_cards'], function($a, $b) {
            return $a['minute'] <=> $b['minute'];
        });
    }

    private function generateInjury(array &$events, Team $team): void
    {
        $players = $team->players->where('is_injured', false);

        if ($players->isEmpty()) {
            return;
        }

        $weights = [];
        foreach ($players as $player) {
            $weights[$player->id] = max(1, 100 - $player->condition);
        }

        $player = $this->selectPlayerByWeights($players, $weights);
        $minute = mt_rand(15, 80);

        $player->is_injured = true;
        $player->save();

        $events['injuries'][] = [
            'minute' => $minute,
            'team' => $team->id == $player->team_id ? 'home' : 'away',
            'player_id' => $player->id,
            'player_name' => $player->name,
        ];
    }

    private function calculateSimplePlayerRatings(array &$events, Team $homeTeam, Team $awayTeam, int $homeScore, int $awayScore): void
    {
        $ratings = [];

        foreach ($homeTeam->players as $player) {
            if ($player->is_injured) continue;

            $rating = 60;
            if ($homeScore > $awayScore) {
                $rating += 10;
            } elseif ($homeScore < $awayScore) {
                $rating -= 5;
            } else {
                $rating += 5;
            }

            $ratings[$player->id] = [
                'player_id' => $player->id,
                'player_name' => $player->name,
                'team' => 'home',
                'goals' => 0,
                'assists' => 0,
                'rating' => $rating
            ];
        }

        foreach ($awayTeam->players as $player) {
            if ($player->is_injured) continue;

            $rating = 60;

            if ($awayScore > $homeScore) {
                $rating += 10;
            } elseif ($awayScore < $homeScore) {
                $rating -= 5;
            } else {
                $rating += 5;
            }

            $ratings[$player->id] = [
                'player_id' => $player->id,
                'player_name' => $player->name,
                'team' => 'away',
                'goals' => 0,
                'assists' => 0,
                'rating' => $rating
            ];
        }

        foreach ($events['home_goals'] as $goal) {
            if (isset($ratings[$goal['scorer_id']])) {
                $ratings[$goal['scorer_id']]['goals']++;
                $ratings[$goal['scorer_id']]['rating'] += 15;
            }

            if ($goal['assister_id'] && isset($ratings[$goal['assister_id']])) {
                $ratings[$goal['assister_id']]['assists']++;
                $ratings[$goal['assister_id']]['rating'] += 10;
            }
        }

        foreach ($events['away_goals'] as $goal) {
            if (isset($ratings[$goal['scorer_id']])) {
                $ratings[$goal['scorer_id']]['goals']++;
                $ratings[$goal['scorer_id']]['rating'] += 15;
            }

            if ($goal['assister_id'] && isset($ratings[$goal['assister_id']])) {
                $ratings[$goal['assister_id']]['assists']++;
                $ratings[$goal['assister_id']]['rating'] += 10;
            }
        }

        foreach ($events['yellow_cards'] as $card) {
            if (isset($ratings[$card['player_id']])) {
                $ratings[$card['player_id']]['rating'] -= 5;
            }
        }

        foreach ($events['red_cards'] as $card) {
            if (isset($ratings[$card['player_id']])) {
                $ratings[$card['player_id']]['rating'] -= 20;
            }
        }

        foreach ($ratings as $playerId => $data) {
            $ratings[$playerId]['rating'] = max(10, min(100, $data['rating']));
        }

        $events['player_ratings'] = $ratings;
    }

    private function selectPlayerByWeights(Collection $players, array $weights)
    {
        if (empty($weights)) {
            return $players->random();
        }

        $totalWeight = array_sum($weights);
        if ($totalWeight <= 0) {
            return $players->random();
        }

        $rand = mt_rand(1, $totalWeight);
        $running = 0;

        foreach ($weights as $playerId => $weight) {
            $running += $weight;
            if ($rand <= $running) {
                $player = $players->firstWhere('id', $playerId);
                return $player ?: $players->random();
            }
        }

        return $players->random();
    }

    public function recordPlayerPerformances(MatchModel $match, Team $homeTeam, Team $awayTeam, array $events): void
    {
        foreach ($events['player_ratings'] as $playerId => $data) {
            PlayerPerformance::create([
                'player_id' => $playerId,
                'match_id' => $match->getKey(),
                'goals_scored' => $data['goals'] ?? 0,
                'assists' => $data['assists'] ?? 0,
                'rating' => $data['rating'] ?? 60,
                'minutes_played' => 90,
            ]);
        }
    }

    public function updateStandings(MatchModel $match): void
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

    public function updatePlayerConditions(Team $homeTeam, Team $awayTeam): void
    {
        $this->updateTeamPlayerConditions($homeTeam);
        $this->updateTeamPlayerConditions($awayTeam);
    }

    private function updateTeamPlayerConditions(Team $team): void
    {
        $players = Player::where('team_id', $team->getKey())->get();

        foreach ($players as $player) {
            if ($player->is_injured) continue;

            $baseConditionDrop = 10;

            if ($player->position === 'STRIKER' || $player->position === 'WINGER') {
                $baseConditionDrop += 5;
            } else if ($player->position === 'MIDFIELDER') {
                $baseConditionDrop += 8;
            } else if ($player->position === 'FULLBACK' || $player->position === 'CENTRE_BACK') {
                $baseConditionDrop += 7;
            } else if ($player->position === 'GOALKEEPER') {
                $baseConditionDrop += 3;
            }

            $player->condition = max(10, $player->condition - $baseConditionDrop);

            $injuryChance = 5 + max(0, (80 - $player->condition) / 4);
            if (mt_rand(1, 100) <= $injuryChance) {
                $player->is_injured = true;
            }

            $player->save();
        }
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
