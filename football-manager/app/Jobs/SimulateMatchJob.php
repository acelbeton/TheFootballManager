<?php

namespace App\Jobs;

use App\Events\MatchStatusUpdate;
use App\Models\LineupPlayer;
use App\Models\MatchEvent;
use App\Models\MatchModel;
use App\Models\MatchSimulationStatus;
use App\Models\Player;
use App\Models\PlayerPerformance;
use App\Models\Standing;
use App\Models\Team;
use App\Models\TeamLineup;
use App\Services\RealtimeMatchSimulationService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Log;
use Throwable;

class SimulateMatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const EVENTS = [
        'SHOT_ON_TARGET' => 30,
        'SHOT_OFF_TARGET' => 25,
        'CORNER' => 15,
        'YELLOW_CARD' => 5,
        'RED_CARD' => 1,
        'GOAL' => 40,
        'SAVE' => 10,
        'TACKLE' => 5
    ];
    const MATCH_SPEED = 1;
    const SIMULATION_CHUNK = 15;
    const FATIGUE_START = 45;
    const FATIGUE_MAX = 0.3;

    protected $match;
    protected $homeTeam;
    protected $awayTeam;
    protected $homePlayers = [];
    protected $awayPlayers = [];
    protected $homePlayerStats = [];
    protected $awayPlayerStats = [];
    protected $currentMinute = 0;
    protected $homeScore = 0;
    protected $awayScore = 0;
    protected $homePossession = 50;
    protected $awayPossession = 50;
    protected $homeShots = 0;
    protected $awayShots = 0;
    protected $homeShotsOnTarget = 0;
    protected $awayShotsOnTarget = 0;
    protected $matchEvents = [];
    protected $playerFatigue = [];
    protected $matchId;

    public function __construct(int $matchId)
    {
        $this->matchId = $matchId;
    }

    /**
     * @throws Exception
     */
    public function handle(RealtimeMatchSimulationService $simulationService): void
    {
        try {
            $this->match = MatchModel::findOrFail($this->matchId);

            $simulationStatus = MatchSimulationStatus::where('match_id', $this->matchId)
                ->orderBy('created_at', 'desc')
                ->first();

            $startMinute = ($simulationStatus && $simulationStatus->current_minute > 0)
                ? $simulationStatus->current_minute
                : 0;

            if ($startMinute == 0) {
                $simulationService->updateMatchStatus($this->match, 'IN_PROGRESS', 0);
                $this->initializeSimulation();

                $payload = [
                    'match_id' => $this->match->getKey(),
                    'type' => 'MATCH_START',
                    'current_minute' => 0,
                    'home_team' => [
                        'id' => $this->homeTeam->getKey(),
                        'name' => $this->homeTeam->name,
                        'score' => 0,
                        'possession' => $this->homePossession,
                        'shots' => 0,
                        'shots_on_target' => 0,
                    ],
                    'away_team' => [
                        'id' => $this->awayTeam->getKey(),
                        'name' => $this->awayTeam->name,
                        'score' => 0,
                        'possession' => $this->awayPossession,
                        'shots' => 0,
                        'shots_on_target' => 0,
                    ],
                    'event' => null,
                    'commentary' => "The match between {$this->homeTeam->name} and {$this->awayTeam->name} is about to begin!",
                ];

                broadcast(new MatchStatusUpdate($payload));

                sleep(1);
            } else {
                $this->initializeSimulation();
                $this->currentMinute = $startMinute;

                $this->homeScore = $this->match->home_team_score;
                $this->awayScore = $this->match->away_team_score;

                $this->loadExistingEvents();
            }

            $endMinute = min(90, $startMinute + self::SIMULATION_CHUNK);

            while ($this->currentMinute < $endMinute) {
                $this->simulateMinute();
                $this->currentMinute++;

                $simulationService->updateMatchStatus($this->match, 'IN_PROGRESS', $this->currentMinute);

                if ($this->currentMinute == 45) {
                    $this->broadcastMatchUpdate('HALF_TIME', null,
                        "Half time! The score is {$this->homeTeam->name} {$this->homeScore} - {$this->awayScore} {$this->awayTeam->name}");

                    sleep(2);
                } else {
                    usleep(self::MATCH_SPEED * 1000000);
                }
            }

            $this->match->update([
                'home_team_score' => $this->homeScore,
                'away_team_score' => $this->awayScore,
            ]);

            if ($this->currentMinute < 90) {
                SimulateMatchJob::dispatch($this->matchId)
                    ->onQueue('match-simulation')
                    ->delay(now()->addSeconds(2));
            } else {
                $this->endMatch($simulationService);
            }
        } catch (Exception $e) {
            Log::error("Error in match simulation: " . $e->getMessage(), [
                'match_id' => $this->matchId,
                'current_minute' => $this->currentMinute ?? 0,
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    private function initializeSimulation(): void
    {
        $this->homeTeam = Team::findOrFail($this->match->home_team_id);
        $this->awayTeam = Team::findOrFail($this->match->away_team_id);

        $this->loadPlayerStats();
        $this->initializeFatigue();

        if ($this->currentMinute == 0) {
            $this->homeScore = 0;
            $this->awayScore = 0;
            $this->calculateInitialPossession();
            $this->homeShots = 0;
            $this->awayShots = 0;
            $this->homeShotsOnTarget = 0;
            $this->awayShotsOnTarget = 0;
            $this->matchEvents = [];
        }
    }

    private function loadExistingEvents(): void
    {
        $events = MatchEvent::where('match_id', $this->matchId)
            ->orderBy('minute', 'asc')
            ->get();

        foreach ($events as $event) {
            if ($event->type === 'GOAL') {
                if ($event->team === 'home') {
                    $this->homeScore = max($this->homeScore, $event->home_score);
                } else {
                    $this->awayScore = max($this->awayScore, $event->away_score);
                }
            } else if ($event->type === 'SHOT_ON_TARGET') {
                if ($event->team === 'home') {
                    $this->homeShotsOnTarget++;
                    $this->homeShots++;
                } else {
                    $this->awayShotsOnTarget++;
                    $this->awayShots++;
                }
            } else if ($event->type === 'SHOT_OFF_TARGET') {
                if ($event->team === 'home') {
                    $this->homeShots++;
                } else {
                    $this->awayShots++;
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    private function loadPlayerStats(): void
    {
        $homeLineup = TeamLineup::where('team_id', $this->homeTeam->getKey())
            ->orderBy('created_at', 'desc')
            ->first();

        $awayLineup = TeamLineup::where('team_id', $this->awayTeam->getKey())
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$homeLineup || !$awayLineup) {
            Log::error("Could not find lineups for match {$this->matchId}");
            throw new Exception("Missing lineup data for match simulation");
        }

        $homeStarterIds = LineupPlayer::where('lineup_id', $homeLineup->getKey())
            ->where('is_starter', 1)
            ->pluck('player_id')
            ->toArray();

        $awayStarterIds = LineupPlayer::where('lineup_id', $awayLineup->getKey())
            ->where('is_starter', 1)
            ->pluck('player_id')
            ->toArray();

        $this->homePlayers = Player::whereIn('id', $homeStarterIds)
            ->where('is_injured', false)
            ->with('statistics')
            ->get();

        $this->awayPlayers = Player::whereIn('id', $awayStarterIds)
            ->where('is_injured', false)
            ->with('statistics')
            ->get();

        if ($this->homePlayers->count() < 11) {
            $additionalHomePlayers = Player::where('team_id', $this->homeTeam->getKey())
                ->where('is_injured', false)
                ->whereNotIn('id', $homeStarterIds)
                ->with('statistics')
                ->limit(11 - $this->homePlayers->count())
                ->get();

            $this->homePlayers = $this->homePlayers->merge($additionalHomePlayers);
            Log::warning("Had to add bench players to home team for match {$this->matchId}");
        }

        if ($this->awayPlayers->count() < 11) {
            $additionalAwayPlayers = Player::where('team_id', $this->awayTeam->getKey())
                ->where('is_injured', false)
                ->whereNotIn('id', $awayStarterIds)
                ->with('statistics')
                ->limit(11 - $this->awayPlayers->count())
                ->get();

            $this->awayPlayers = $this->awayPlayers->merge($additionalAwayPlayers);
            Log::warning("Had to add bench players to away team for match {$this->matchId}");
        }

        foreach ($this->homePlayers as $player) {
            $stats = $player->statistics;
            if ($stats) {
                $this->homePlayerStats[$player->getKey()] = [
                    'id' => $player->getKey(),
                    'attacking' => $stats->attacking,
                    'defending' => $stats->defending,
                    'stamina' => $stats->stamina,
                    'technical_skills' => $stats->technical_skills,
                    'speed' => $stats->speed,
                    'tactical_sense' => $stats->tactical_sense,
                    'position' => $player->position,
                    'condition' => $player->condition,
                ];
            }
        }

        foreach ($this->awayPlayers as $player) {
            $stats = $player->statistics;
            if ($stats) {
                $this->awayPlayerStats[$player->getKey()] = [
                    'id' => $player->getKey(),
                    'attacking' => $stats->attacking,
                    'defending' => $stats->defending,
                    'stamina' => $stats->stamina,
                    'technical_skills' => $stats->technical_skills,
                    'speed' => $stats->speed,
                    'tactical_sense' => $stats->tactical_sense,
                    'position' => $player->position,
                    'condition' => $player->condition,
                ];
            }
        }
    }

    private function initializeFatigue(): void
    {
        foreach ($this->homePlayers as $player) {
            $this->playerFatigue[$player->getKey()] = 0;
        }

        foreach ($this->awayPlayers as $player) {
            $this->playerFatigue[$player->getKey()] = 0;
        }
    }

    private function calculateInitialPossession(): void
    {
        $homeTeamRating = 0;
        $awayTeamRating = 0;

        foreach ($this->homePlayers as $player) {
            $stats = $this->homePlayerStats[$player->getKey()] ?? null;
            if ($stats) {
                $homeTeamRating += ($stats['technical_skills'] + $stats['tactical_sense']) / 2;
            }
        }

        foreach ($this->awayPlayers as $player) {
            $stats = $this->awayPlayerStats[$player->getKey()] ?? null;
            if ($stats) {
                $awayTeamRating += ($stats['technical_skills'] + $stats['tactical_sense']) / 2;
            }
        }

        $homeTeamRating *= 1.1;

        $totalRating = $homeTeamRating + $awayTeamRating;
        if ($totalRating > 0) {
            $this->homePossession = round(($homeTeamRating / $totalRating) * 100);
        } else {
            $this->homePossession = 55;
        }

        if ($this->homeTeam->current_tactic === 'ATTACK_MODE') {
            $this->homePossession += 5;
        } elseif ($this->homeTeam->current_tactic === 'DEFEND_MODE') {
            $this->homePossession -= 10;
        }

        if ($this->awayTeam->current_tactic === 'ATTACK_MODE') {
            $this->homePossession -= 5;
        } elseif ($this->awayTeam->current_tactic === 'DEFEND_MODE') {
            $this->homePossession += 5;
        }

        $this->homePossession = max(30, min(65, $this->homePossession));
        $this->awayPossession = 100 - $this->homePossession;
    }

    private function updatePlayerFatigue(): void
    {
        foreach ($this->homePlayers as $player) {
            $stats = $this->homePlayerStats[$player->getKey()] ?? null;
            if ($stats) {
                $fatigueRate = 0.5 - ($stats['stamina'] / 200);
                $minuteFactor = ($this->currentMinute / 90) * 0.6;

                $this->playerFatigue[$player->getKey()] += $fatigueRate * $minuteFactor;
                $this->playerFatigue[$player->getKey()] = min(self::FATIGUE_MAX, $this->playerFatigue[$player->getKey()]);
            }
        }

        foreach ($this->awayPlayers as $player) {
            $stats = $this->awayPlayerStats[$player->getKey()] ?? null;
            if ($stats) {
                $fatigueRate = 0.5 - ($stats['stamina'] / 200);
                $minuteFactor = ($this->currentMinute / 90) * 0.6;

                $this->playerFatigue[$player->getKey()] += $fatigueRate * $minuteFactor;
                $this->playerFatigue[$player->getKey()] = min(self::FATIGUE_MAX, $this->playerFatigue[$player->getKey()]);
            }
        }
    }

    private function simulateMinute(): void
    {
        if ($this->currentMinute >= self::FATIGUE_START) {
            $this->updatePlayerFatigue();
        }

        $this->updatePossession();
        $this->broadcastMatchUpdate('TIME_UPDATE', null, null);

        usleep(100000);

        $eventChance = 25;

        if ($this->currentMinute >= 43 && $this->currentMinute <= 47) {
            $eventChance = 45;
        } else if ($this->currentMinute >= 85) {
            $eventChance = 45;
        }

        if ($this->currentMinute >= 80 && $this->homeScore == $this->awayScore) {
            $eventChance += 15;
        }

        $attackingTeam = (rand(1, 100) <= $this->homePossession) ? 'home' : 'away';

        if (rand(1, 100) <= $eventChance) {
            $this->generateEvent($attackingTeam);
        }
    }

    private function updatePossession(): void
    {
        $maxChange = 2;
        $homeAdvantage = 0;
        $homeAdvantage += 1;

        if ($this->homeTeam->current_tactic === 'ATTACK_MODE') {
            $homeAdvantage += 1;
        } elseif ($this->homeTeam->current_tactic === 'DEFEND_MODE') {
            $homeAdvantage -= 2;
        }

       if ($this->awayTeam->current_tactic === 'ATTACK_MODE') {
            $homeAdvantage -= 1;
       } elseif ($this->awayTeam->current_tactic === 'DEFEND_MODE') {
            $homeAdvantage += 2;
       }

        $scoreDifference = $this->homeScore - $this->awayScore;
        if ($scoreDifference > 0) {
            $homeAdvantage -= min(3, $scoreDifference);
        } elseif ($scoreDifference < 0) {
            $homeAdvantage += min(3, abs($scoreDifference));
        }

        $randomFactor = rand(-$maxChange, $maxChange);

        $possessionChange = $homeAdvantage + $randomFactor;

        $this->homePossession += $possessionChange;

        $this->homePossession = max(30, min(70, $this->homePossession));
        $this->awayPossession = 100 - $this->homePossession;

        if (abs($possessionChange) > 2) {
            Log::debug("Significant possession change: {$possessionChange}%", [
                'match_id' => $this->match->getKey(),
                'minute' => $this->currentMinute,
                'home_possession' => $this->homePossession,
                'away_possession' => $this->awayPossession,
            ]);
        }
    }

    private function generateEvent(string $attackingTeam)
    {
        $eventType = $this->selectRandomEvent();

        $attackingPlayer = $this->getPlayerForAction($attackingTeam, $eventType);
        $defendingPlayer = $this->getDefendingPlayer($attackingTeam === 'home' ? 'away' : 'home', $eventType);

        if (!$attackingPlayer || !$defendingPlayer) {
            return;
        }

        $secondAttacker = null;
        if ($eventType === 'GOAL' && rand(1, 100) <= 70) {
            $secondAttacker = $this->getPlayerForAction($attackingTeam, 'ASSIST', [$attackingPlayer->getKey()]);
        }

        switch ($eventType) {
            case 'GOAL':
                $scoreChance = 50;

                if ($attackingTeam === 'home' && $this->homeTeam->current_tactic === 'ATTACK_MODE') {
                    $scoreChance += 10;
                } elseif ($attackingTeam === 'away' && $this->awayTeam->current_tactic === 'ATTACK_MODE') {
                    $scoreChance += 10;
                }

                $attackerStats = $attackingTeam === 'home' ?
                    $this->homePlayerStats[$attackingPlayer->getKey()] :
                    $this->awayPlayerStats[$attackingPlayer->getKey()];

                $defenderStats = $attackingTeam === 'home' ?
                    $this->awayPlayerStats[$defendingPlayer->getKey()] :
                    $this->homePlayerStats[$defendingPlayer->getKey()];

                $scoreChance += ($attackerStats['attacking'] - $defenderStats['defending']) / 5;

                if ($attackingTeam === 'home') {
                    $scoreChance += 5;
                }

                if (($attackingTeam === 'home' && $this->homeScore < $this->awayScore) ||
                    ($attackingTeam === 'away' && $this->awayScore < $this->homeScore)) {
                    $scoreChance += 10;
                }

                $scoreChance = max(20, min(80, $scoreChance));

                if (rand(1, 100) <= $scoreChance) {
                    if ($attackingTeam === 'home') {
                        $this->homeScore++;
                    } else {
                        $this->awayScore++;
                    }

                    $commentary = $this->generateGoalCommentary($attackingPlayer, $secondAttacker, $defendingPlayer);

                    $this->recordEvent(
                        'GOAL',
                        $attackingTeam,
                        $attackingPlayer,
                        $secondAttacker,
                        $commentary
                    );
                } else {
                    $commentary = "Shot by {$attackingPlayer->name}, but it's saved by {$defendingPlayer->name}.";

                    $this->recordEvent(
                        'SAVE',
                        $attackingTeam === 'home' ? 'away' : 'home',
                        $defendingPlayer,
                        $attackingPlayer,
                        $commentary
                    );
                }
                break;

            case 'SHOT_ON_TARGET':
                if ($attackingTeam === 'home') {
                    $this->homeShots++;
                    $this->homeShotsOnTarget++;
                } else {
                    $this->awayShots++;
                    $this->awayShotsOnTarget++;
                }

                $commentary = "{$attackingPlayer->name} fires a shot on target, but {$defendingPlayer->name} makes the save.";

                $this->recordEvent(
                    'SHOT_ON_TARGET',
                    $attackingTeam,
                    $attackingPlayer,
                    null,
                    $commentary
                );
                break;

            case 'SHOT_OFF_TARGET':
                if ($attackingTeam === 'home') {
                    $this->homeShots++;
                } else {
                    $this->awayShots++;
                }

                $commentary = "{$attackingPlayer->name} tries to shoot but it goes wide of the goal.";

                $this->recordEvent(
                    'SHOT_OFF_TARGET',
                    $attackingTeam,
                    $attackingPlayer,
                    null,
                    $commentary
                );
                break;

            case 'CORNER':
                $commentary = "Corner kick for " . ($attackingTeam === 'home' ? $this->homeTeam->name : $this->awayTeam->name) .
                    ". {$attackingPlayer->name} will take it.";

                $this->recordEvent(
                    'CORNER',
                    $attackingTeam,
                    $attackingPlayer,
                    null,
                    $commentary
                );

                if (rand(1, 100) <= 30) {
                    $this->generateEvent($attackingTeam);
                }
                break;

            case 'YELLOW_CARD':
                $commentary = "Yellow card! {$attackingPlayer->name} commits a foul on {$defendingPlayer->name}.";

                $this->recordEvent(
                    'YELLOW_CARD',
                    $attackingTeam,
                    $attackingPlayer,
                    $defendingPlayer,
                    $commentary
                );
                break;

            case 'RED_CARD':
                $commentary = "RED CARD! {$attackingPlayer->name} is sent off for a serious foul on {$defendingPlayer->name}.";

                $this->recordEvent(
                    'RED_CARD',
                    $attackingTeam,
                    $attackingPlayer,
                    $defendingPlayer,
                    $commentary
                );
                break;

            case 'SAVE':
                $commentary = "Great save by {$defendingPlayer->name} to deny {$attackingPlayer->name}!";

                $this->recordEvent(
                    'SAVE',
                    $attackingTeam === 'home' ? 'away' : 'home',
                    $defendingPlayer,
                    $attackingPlayer,
                    $commentary
                );
                break;

            case 'TACKLE':
                $commentary = "{$defendingPlayer->name} makes a clean tackle to win the ball from {$attackingPlayer->name}.";

                $this->recordEvent(
                    'TACKLE',
                    $attackingTeam === 'home' ? 'away' : 'home',
                    $defendingPlayer,
                    $attackingPlayer,
                    $commentary
                );
                break;
        }
    }

    private function getPlayerForAction(string $team, string $actionType, array $excludePlayers = []): ?Player
    {
        $players = ($team === 'home') ? $this->homePlayers : $this->awayPlayers;

        $players = $players->whereNotIn('id', $excludePlayers);

        if ($players->isEmpty()) {
            return null;
        }

        $positionPreferences = [];

        switch ($actionType) {
            case 'GOAL':
            case 'SHOT_ON_TARGET':
            case 'SHOT_OFF_TARGET':
                $positionWeights = [
                    'STRIKER' => 10,
                    'WINGER' => 6,
                    'MIDFIELDER' => 3,
                    'FULLBACK' => 1,
                    'CENTRE_BACK' => 0.5,
                    'GOALKEEPER' => 0.1
                ];
                break;

            case 'ASSIST':
                $positionWeights = [
                    'MIDFIELDER' => 10,
                    'WINGER' => 8,
                    'STRIKER' => 5,
                    'FULLBACK' => 3,
                    'CENTRE_BACK' => 1,
                    'GOALKEEPER' => 0.2
                ];
                break;

            case 'CORNER':
                $positionWeights = [
                    'MIDFIELDER' => 10,
                    'WINGER' => 8,
                    'FULLBACK' => 5,
                    'STRIKER' => 3,
                    'CENTRE_BACK' => 1,
                    'GOALKEEPER' => 0
                ];
                break;

            case 'YELLOW_CARD':
            case 'RED_CARD':
            case 'TACKLE':
                $positionWeights = [
                    'CENTRE_BACK' => 10,
                    'FULLBACK' => 8,
                    'MIDFIELDER' => 6,
                    'WINGER' => 3,
                    'STRIKER' => 2,
                    'GOALKEEPER' => 0.5
                ];
                break;

            default:
                $positionWeights = [
                    'STRIKER' => 1,
                    'WINGER' => 1,
                    'MIDFIELDER' => 1,
                    'FULLBACK' => 1,
                    'CENTRE_BACK' => 1,
                    'GOALKEEPER' => 1
                ];
        }

        $playerWeights = [];

        foreach ($players as $player) {
            $positionWeight = $positionWeights[$player->position] ?? 1;

            $playerWeights[$player->getKey()] = $positionWeight * (0.7 + (0.3 * $player->rating/100));
        }

        $totalWeight = array_sum($playerWeights);
        if ($totalWeight <= 0) {
            return $players->random();
        }

        $randomValue = mt_rand(1, $totalWeight * 100) / 100;
        $cumulativeWeight = 0;

        foreach ($playerWeights as $playerId => $weight) {
            $cumulativeWeight += $weight;
            if ($randomValue <= $cumulativeWeight) {
                return $players->firstWhere('id', $playerId);
            }
        }

        return $players->random();
    }

    private function getDefendingPlayer(string $team, string $actionType): ?Player
    {
        $players = ($team === 'home') ? $this->homePlayers : $this->awayPlayers;

        if ($players->isEmpty()) {
            return null;
        }

        $positionWeights = [];

        if (in_array($actionType, ['GOAL', 'SHOT_ON_TARGET', 'SAVE'])) {
            $positionWeights = [
                'GOALKEEPER' => 50,
                'CENTRE_BACK' => 0.5,
                'FULLBACK' => 0.3,
                'MIDFIELDER' => 0.1,
                'WINGER' => 0.05,
                'STRIKER' => 0.01
            ];
        } else {
            $positionWeights = [
                'CENTRE_BACK' => 10,
                'FULLBACK' => 8,
                'MIDFIELDER' => 5,
                'WINGER' => 2,
                'STRIKER' => 1,
                'GOALKEEPER' => 0.5
            ];
        }

        $playerWeights = [];

        foreach ($players as $player) {
            $positionWeight = $positionWeights[$player->position] ?? 1;
            $playerStats = ($team === 'home') ? $this->homePlayerStats[$player->getKey()] : $this->awayPlayerStats[$player->getKey()];
            $defenseRating = $playerStats['defending'] ?? 50;
            $playerWeights[$player->getKey()] = $positionWeight * (0.6 + (0.4 * $defenseRating/100));
        }

        $totalWeight = array_sum($playerWeights);
        if ($totalWeight <= 0) {
            return $players->random();
        }

        $randValue = mt_rand(1, $totalWeight * 100) / 100;
        $cumulativeWeight = 0;

        foreach ($playerWeights as $playerId => $weight) {
            $cumulativeWeight += $weight;
            if ($randValue <= $cumulativeWeight) {
                return $players->firstWhere('id', $playerId);
            }
        }

        return $players->random();
    }

    private function recordEvent(string $type, string $team, ?Player $mainPlayer, ?Player $secondaryPlayer, string $commentary)
    {
        $event = [
            'type' => $type,
            'minute' => $this->currentMinute,
            'team' => $team,
            'main_player_id' => $mainPlayer?->getKey(),
            'main_player_name' => $mainPlayer?->name,
            'secondary_player_id' => $secondaryPlayer?->getKey(),
            'secondary_player_name' => $secondaryPlayer?->name,
            'commentary' => $commentary,
            'home_score' => $this->homeScore,
            'away_score' => $this->awayScore,
        ];

        $this->matchEvents[] = $event;

        MatchEvent::create(array_merge($event, ['match_id' => $this->match->getKey()]));

        $this->broadcastMatchUpdate('EVENT', $event, $commentary);
    }

    private function broadcastMatchUpdate(string $updateType, ?array $event, ?string $commentary): void
    {
        $homeScore = max($this->homeScore, $this->match->home_team_score);
        $awayScore = max($this->awayScore, $this->match->away_team_score);

        if ($this->match->home_team_score != $homeScore || $this->match->away_team_score != $awayScore) {
            $this->match->update([
                'home_team_score' => $homeScore,
                'away_team_score' => $awayScore
            ]);
        }

        $payload = [
            'match_id' => $this->match->getKey(),
            'type' => $updateType,
            'current_minute' => $this->currentMinute,
            'home_team' => [
                'id' => $this->homeTeam->getKey(),
                'name' => $this->homeTeam->name,
                'score' => $homeScore,
                'possession' => $this->homePossession,
                'shots' => $this->homeShots,
                'shots_on_target' => $this->homeShotsOnTarget,
            ],
            'away_team' => [
                'id' => $this->awayTeam->getKey(),
                'name' => $this->awayTeam->name,
                'score' => $awayScore,
                'possession' => $this->awayPossession,
                'shots' => $this->awayShots,
                'shots_on_target' => $this->awayShotsOnTarget,
            ],
            'event' => $event,
            'commentary' => $commentary,
        ];

        Log::debug("Broadcasting match update", [
            'type' => $updateType,
            'minute' => $this->currentMinute,
            'scores' => "{$homeScore} - {$awayScore}",
            'event_type' => $event ? $event['type'] : 'none'
        ]);

        broadcast(new MatchStatusUpdate($payload));

        usleep(50000);
    }

    private function endMatch(RealtimeMatchSimulationService $simulationService): void
    {
        $this->match->update([
            'home_team_score' => $this->homeScore,
            'away_team_score' => $this->awayScore,
            'home_possession' => $this->homePossession,
            'away_possession' => $this->awayPossession,
            'home_shots' => $this->homeShots,
            'away_shots' => $this->awayShots,
            'home_shots_on_target' => $this->homeShotsOnTarget,
            'away_shots_on_target' => $this->awayShotsOnTarget,
        ]);

        $this->match->refresh();

        $this->recordPlayerPerformances();
        $this->updateStandings();

        $simulationService->updateMatchStatus($this->match, 'COMPLETED', 90);

        $this->broadcastMatchUpdate(
            'MATCH_END',
            null,
            "Full time! The match ends {$this->homeTeam->name} {$this->homeScore} - {$this->awayScore} {$this->awayTeam->name}"
        );

        $finalStatsUpdate = [
            'match_id' => $this->match->getKey(),
            'type' => 'FINAL_STATS_CONFIRMATION',
            'current_minute' => 90,
            'home_team' => [
                'id' => $this->homeTeam->getKey(),
                'name' => $this->homeTeam->name,
                'score' => $this->match->home_team_score,
                'possession' => $this->homePossession,
                'shots' => $this->homeShots,
                'shots_on_target' => $this->homeShotsOnTarget,
            ],
            'away_team' => [
                'id' => $this->awayTeam->getKey(),
                'name' => $this->awayTeam->name,
                'score' => $this->match->away_team_score,
                'possession' => $this->awayPossession,
                'shots' => $this->awayShots,
                'shots_on_target' => $this->awayShotsOnTarget,
            ],
        ];

        broadcast(new MatchStatusUpdate($finalStatsUpdate));

        Log::info("Match {$this->match->getKey()} completed. Final stats saved.", [
            'score' => "{$this->homeScore} - {$this->awayScore}",
            'possession' => "{$this->homePossession}% - {$this->awayPossession}%",
            'shots' => "{$this->homeShots} - {$this->awayShots}",
            'shots_on_target' => "{$this->homeShotsOnTarget} - {$this->awayShotsOnTarget}",
        ]);
    }

    public function failed(Throwable $exception)
    {
        $simulationService = app(RealtimeMatchSimulationService::class);
        $match = MatchModel::find($this->matchId);

        if ($match) {
            $simulationService->updateMatchStatus($match, 'FAILED', $this->currentMinute ?? 0);
        }

        Log::error('Match simulation failed', [
            'match_id' => $this->matchId,
            'exception' => $exception->getMessage(),
        ]);
    }

    private function recordPlayerPerformances(): void
    {
        $playerStats = [];

        foreach ($this->homePlayers as $player) {
            $playerStats[$player->getKey()] = [
                'goals' => 0,
                'assists' => 0,
                'shots' => 0,
                'shots_on_target' => 0,
                'saves' => 0,
                'yellow_cards' => 0,
                'red_cards' => 0,
                'tackles' => 0,
                'interceptions' => 0,
                'pass_completions' => 0,
                'rating_points' => 60,
            ];
        }

        foreach ($this->awayPlayers as $player) {
            $playerStats[$player->getKey()] = [
                'goals' => 0,
                'assists' => 0,
                'shots' => 0,
                'shots_on_target' => 0,
                'saves' => 0,
                'yellow_cards' => 0,
                'red_cards' => 0,
                'tackles' => 0,
                'interceptions' => 0,
                'pass_completions' => 0,
                'rating_points' => 60,
            ];
        }

        foreach ($this->matchEvents as $event) {
            if ($event['main_player_id']) {
                if (!isset($playerStats[$event['main_player_id']])) {
                    continue;
                }

                switch ($event['type']) {
                    case 'GOAL':
                        $playerStats[$event['main_player_id']]['goals']++;
                        $playerStats[$event['main_player_id']]['rating_points'] += 15;

                        if ($event['secondary_player_id'] && isset($playerStats[$event['secondary_player_id']])) {
                            $playerStats[$event['secondary_player_id']]['assists']++;
                            $playerStats[$event['secondary_player_id']]['rating_points'] += 10;
                        }
                        break;

                    case 'SHOT_ON_TARGET':
                        $playerStats[$event['main_player_id']]['shots']++;
                        $playerStats[$event['main_player_id']]['shots_on_target']++;
                        $playerStats[$event['main_player_id']]['rating_points'] += 2;
                        break;

                    case 'SHOT_OFF_TARGET':
                        $playerStats[$event['main_player_id']]['shots']++;
                        $playerStats[$event['main_player_id']]['rating_points'] += 1;
                        break;

                    case 'GREAT_SAVE':
                        $playerStats[$event['main_player_id']]['saves']++;
                        $playerStats[$event['main_player_id']]['rating_points'] += 5;
                        break;

                    case 'TACKLE':
                        $playerStats[$event['main_player_id']]['tackles']++;
                        $playerStats[$event['main_player_id']]['rating_points'] += 3;
                        break;

                    case 'INTERCEPTION':
                        $playerStats[$event['main_player_id']]['interceptions']++;
                        $playerStats[$event['main_player_id']]['rating_points'] += 2;
                        break;

                    case 'GOOD_PASS':
                        $playerStats[$event['main_player_id']]['pass_completions']++;
                        $playerStats[$event['main_player_id']]['rating_points'] += 1;
                        break;

                    case 'YELLOW_CARD':
                        $playerStats[$event['main_player_id']]['yellow_cards']++;
                        $playerStats[$event['main_player_id']]['rating_points'] -= 5;
                        break;

                    case 'RED_CARD':
                        $playerStats[$event['main_player_id']]['red_cards']++;
                        $playerStats[$event['main_player_id']]['rating_points'] -= 15;
                        break;

                    case 'SKILL_MOVE':
                        $playerStats[$event['main_player_id']]['rating_points'] += 2;
                        break;
                }
            }
        }

        foreach ($playerStats as $playerId => $stats) {
            $rating = max(1, min(100, $stats['rating_points']));

            $player = Player::find($playerId);
            if ($player) {
                if (($player->team_id == $this->homeTeam->getKey() && $this->homeScore > $this->awayScore) ||
                    ($player->team_id == $this->awayTeam->getKey() && $this->awayScore > $this->homeScore)) {
                    $rating += 5;
                } elseif ($this->homeScore == $this->awayScore) {
                    $rating += 2;
                }

                $player->condition = max(10, $player->condition - rand(5, 15));
                $player->save();

                PlayerPerformance::create([
                    'player_id' => $playerId,
                    'match_id' => $this->match->getKey(),
                    'goals_scored' => $stats['goals'],
                    'assists' => $stats['assists'],
                    'rating' => $rating,
                    'minutes_played' => 90,
                ]);
            }
        }
    }

    private function updateStandings(): void
    {
        $homeStanding = Standing::firstOrCreate([
            'season_id' => $this->homeTeam->season_id,
            'team_id' => $this->homeTeam->getKey(),
        ]);

        $awayStanding = Standing::firstOrCreate([
            'season_id' => $this->awayTeam->season_id,
            'team_id' => $this->awayTeam->getKey(),
        ]);

        $homeStanding->matches_played += 1;
        $awayStanding->matches_played += 1;

        $homeStanding->goals_scored += $this->homeScore;
        $homeStanding->goals_conceded += $this->awayScore;
        $awayStanding->goals_scored += $this->awayScore;
        $awayStanding->goals_conceded += $this->homeScore;

        if ($this->homeScore > $this->awayScore) {
            $homeStanding->matches_won += 1;
            $homeStanding->points += 3;
            $awayStanding->matches_lost += 1;
        } elseif ($this->homeScore < $this->awayScore) {
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

    private function selectRandomEvent(): string
    {
        $totalWeight = array_sum(self::EVENTS);
        $rand = mt_rand(1, $totalWeight);

        $currentWeight = 0;
        foreach (self::EVENTS as $event => $weight) {
            $currentWeight += $weight;
            if ($rand <= $currentWeight) {
                return $event;
            }
        }

        return array_key_first(self::EVENTS);
    }

    private function generateGoalCommentary(Player $scorer, ?Player $assister, Player $goalkeeper): string
    {
        $templates = [
            "GOAL! {scorer} finds the back of the net! {team} lead {score}!",
            "GOAL! What a finish by {scorer}! {team} now ahead {score}!",
            "GOAL! {scorer} scores for {team}! The score is now {score}!",
        ];

        if ($assister) {
            $assistTemplates = [
                "GOAL! {scorer} scores after a brilliant pass from {assister}! {team} {score}!",
                "GOAL! {assister} with a perfect assist and {scorer} finishes it beautifully! {team} {score}!",
                "GOAL! {scorer} puts it away! Great work by {assister} to set it up! {team} {score}!",
            ];
            $templates = array_merge($templates, $assistTemplates);
        }

        $template = $templates[array_rand($templates)];

        $team = ($scorer->team_id === $this->homeTeam->getKey()) ? $this->homeTeam->name : $this->awayTeam->name;
        $score = ($scorer->team_id === $this->homeTeam->getKey())
            ? "{$this->homeScore}-{$this->awayScore}"
            : "{$this->awayScore}-{$this->homeScore}";

        return strtr($template, [
            '{scorer}' => $scorer->name,
            '{assister}' => $assister ? $assister->name : '',
            '{goalkeeper}' => $goalkeeper->name,
            '{team}' => $team,
            '{score}' => $score,
        ]);
    }
}
