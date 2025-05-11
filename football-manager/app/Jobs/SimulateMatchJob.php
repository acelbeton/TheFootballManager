<?php

namespace App\Jobs;

use App\Events\MatchStatusUpdate;
use App\Models\MatchEvent;
use App\Models\MatchModel;
use App\Models\MatchSimulationStatus;
use App\Models\Player;
use App\Models\PlayerPerformance;
use App\Models\Standing;
use App\Models\Team;
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
        'SHOT_ON_TARGET' => 25,
        'SHOT_OFF_TARGET' => 30,
        'CORNER' => 20,
        'FREE_KICK' => 15,
        'YELLOW_CARD' => 8,
        'RED_CARD' => 2,
        'INJURY' => 5,
        'SUBSTITUTION' => 10,
        'GREAT_SAVE' => 15,
        'OFFSIDE' => 12,
        'SKILL_MOVE' => 10,
        'GOOD_PASS' => 20,
        'TACKLE' => 15,
        'GOAL' => 70,
        'INTERCEPTION' => 12,
    ];
    const MATCH_SPEED = 1;
    const SIMULATION_CHUNK = 10;
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

    /**
     * Create a new job instance.
     */
    public function __construct(int $matchId)
    {
        $this->matchId = $matchId;
    }

    /**
     * Execute the job.
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
                    $this->halftimeAdjustments();

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

    private function loadPlayerStats(): void
    {
        $this->homePlayers = Player::where('team_id', $this->homeTeam->getKey())
            ->where('is_injured', false)
            ->with('statistics')
            ->get();

        $this->awayPlayers = Player::where('team_id', $this->awayTeam->getKey())
            ->where('is_injured', false)
            ->with('statistics')
            ->get();

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
        $homeMidfielders = $this->homePlayers->where('position', 'MIDFIELDER');
        $awayMidfielders = $this->awayPlayers->where('position', 'MIDFIELDER');

        $homeMidfieldRating = 0;
        foreach ($homeMidfielders as $player) {
            $stats = $this->homePlayerStats[$player->getKey()] ?? null;
            if ($stats) {
                $homeMidfieldRating += ($stats['technical_skills'] * 0.4) +
                    ($stats['tactical_sense'] * 0.3) +
                    ($stats['stamina'] * 0.2) +
                    ($stats['speed'] * 0.1);
            }
        }

        $awayMidfieldRating = 0;
        foreach ($awayMidfielders as $player) {
            $stats = $this->awayPlayerStats[$player->getKey()] ?? null;
            if ($stats) {
                $awayMidfieldRating += ($stats['technical_skills'] * 0.4) +
                    ($stats['tactical_sense'] * 0.3) +
                    ($stats['stamina'] * 0.2) +
                    ($stats['speed'] * 0.1);
            }
        }

        $homeMidfieldRating *= 1.05;

        $totalRating = $homeMidfieldRating + $awayMidfieldRating;
        if ($totalRating > 0) {
            $this->homePossession = round(($homeMidfieldRating / $totalRating) * 100);
            $this->awayPossession = 100 - $this->homePossession;
        } else {
            $this->homePossession = 50;
            $this->awayPossession = 50;
        }

        if ($this->homeTeam->current_tactic === 'POSSESSION_MODE') {
            $this->homePossession += 10;
            $this->awayPossession -= 10;
        } elseif ($this->awayTeam->current_tactic === 'POSSESSION_MODE') {
            $this->awayPossession += 10;
            $this->homePossession -= 10;
        }

        $this->homePossession = max(30, min(70, $this->homePossession));
        $this->awayPossession = 100 - $this->homePossession;
    }

    private function updatePlayerFatigue()
    {
        foreach ($this->homePlayers as $player) {
            $stats = $this->homePlayerStats[$player->getKey()] ?? null;
            if ($stats) {
                $fatigueRate = (100 - $stats['stamina']) / 1000;
                $this->playerFatigue[$player->getKey()] += $fatigueRate * (($this->currentMinute - self::FATIGUE_START) / 10);

                $this->playerFatigue[$player->getKey()] = min(self::FATIGUE_MAX, $this->playerFatigue[$player->getKey()]);
            }
        }

        foreach ($this->awayPlayers as $player) {
            $stats = $this->awayPlayerStats[$player->getKey()] ?? null;
            if ($stats) {
                $fatigueRate = (100 - $stats['stamina']) / 1000;
                $this->playerFatigue[$player->getKey()] += $fatigueRate * (($this->currentMinute - self::FATIGUE_START) / 10);
                $this->playerFatigue[$player->getKey()] = min(self::FATIGUE_MAX, $this->playerFatigue[$player->getKey()]);
            }
        }
    }

    private function halftimeAdjustments(): void
    {
        if ($this->homeTeam->user_id === null) {
            if ($this->homeScore < $this->awayScore - 1) {
                $this->homeTeam->current_tactic = 'ATTACK_MODE';
                $this->homeTeam->save();

                $this->broadcastMatchUpdate('TACTICAL_CHANGE', null,
                    "{$this->homeTeam->name} switches to an attacking formation for the second half!");
            } elseif ($this->homeScore > $this->awayScore + 1) {
                $this->homeTeam->current_tactic = 'DEFEND_MODE';
                $this->homeTeam->save();

                $this->broadcastMatchUpdate('TACTICAL_CHANGE', null,
                    "{$this->homeTeam->name} adopts a more defensive approach for the second half!");
            }
        }

        if ($this->awayTeam->user_id === null) {
            if ($this->awayScore < $this->homeScore - 1) {
                $this->awayTeam->current_tactic = 'ATTACK_MODE';
                $this->awayTeam->save();

                $this->broadcastMatchUpdate('TACTICAL_CHANGE', null,
                    "{$this->awayTeam->name} switches to an attacking formation for the second half!");
            } elseif ($this->awayScore > $this->homeScore + 1) {
                $this->awayTeam->current_tactic = 'DEFEND_MODE';
                $this->awayTeam->save();

                $this->broadcastMatchUpdate('TACTICAL_CHANGE', null,
                    "{$this->awayTeam->name} adopts a more defensive approach for the second half!");
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

        if ($this->homeTeam->current_tactic === 'POSSESSION_MODE') {
            $homeAdvantage += 2;
        } elseif ($this->homeTeam->current_tactic === 'ATTACK_MODE') {
            $homeAdvantage += 1;
        } elseif ($this->homeTeam->current_tactic === 'DEFEND_MODE') {
            $homeAdvantage -= 2;
        }

        if ($this->awayTeam->current_tactic === 'POSSESSION_MODE') {
            $homeAdvantage -= 2;
        } elseif ($this->awayTeam->current_tactic === 'ATTACK_MODE') {
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

        $attacker = ($attackingTeam === 'home') ? $this->homeTeam : $this->awayTeam;
        $defender = ($attackingTeam === 'home') ? $this->awayTeam : $this->homeTeam;

        $attackingPlayer = $this->getPlayerForAction($attackingTeam, $eventType);
        $defendingPlayer = $this->getDefendingPlayer($attackingTeam === 'home' ? 'away' : 'home', $eventType);
        $secondAttacker = $this->getPlayerForAction($attackingTeam, 'ASSIST', [$attackingPlayer->getKey()]);

        switch ($eventType) {
            case 'GOAL':
                if ($this->attemptGoal($attackingTeam, $attackingPlayer, $defendingPlayer)) {
                    if ($attackingTeam === 'home') {
                        $this->homeScore++;
                    } else {
                        $this->awayScore++;
                    }

                    $commentary = $this->generateGoalCommentary(
                        $attackingPlayer,
                        $secondAttacker,
                        $defendingPlayer
                    );

                    $this->recordEvent(
                        'GOAL',
                        $attackingTeam,
                        $attackingPlayer,
                        $secondAttacker,
                        $commentary
                    );
                } else {
                    $commentary = $this->generateSavedShotCommentary(
                        $attackingPlayer,
                        $defendingPlayer
                    );

                    $this->recordEvent(
                        'SHOT_SAVED',
                        $attackingTeam,
                        $attackingPlayer,
                        null,
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

                $commentary = $this->generateShotOnTargetCommentary(
                    $attackingPlayer,
                    $defendingPlayer
                );

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

                $commentary = $this->generateShotOffTargetCommentary(
                    $attackingPlayer
                );

                $this->recordEvent(
                    'SHOT_OFF_TARGET',
                    $attackingTeam,
                    $attackingPlayer,
                    null,
                    $commentary
                );
                break;

            case 'SKILL_MOVE':
                $stats = $attackingTeam === 'home' ?
                    $this->homePlayerStats[$attackingPlayer->getKey()] :
                    $this->awayPlayerStats[$attackingPlayer->getKey()];

                $defenderStats = $attackingTeam === 'home' ?
                    $this->awayPlayerStats[$defendingPlayer->getKey()] :
                    $this->homePlayerStats[$defendingPlayer->getKey()];

                $success = $this->calculateSkillMoveSuccess($stats, $defenderStats);

                if ($success) {
                    $commentary = "{$attackingPlayer->name} shows brilliant skill to beat {$defendingPlayer->name}!";
                } else {
                    $commentary = "{$attackingPlayer->name} tries to take on {$defendingPlayer->name}, but loses possession.";
                }

                $this->recordEvent(
                    'SKILL_MOVE',
                    $attackingTeam,
                    $attackingPlayer,
                    $defendingPlayer,
                    $commentary
                );

                if ($success && rand(1, 100) <= 40) {
                    $this->generateEvent($attackingTeam);
                }
                break;

            case 'TACKLE':
                $defenderStats = $attackingTeam === 'home' ?
                    $this->awayPlayerStats[$defendingPlayer->getKey()] :
                    $this->homePlayerStats[$defendingPlayer->getKey()];

                $defenseRating = $defenderStats['defending'];
                $tackleSuccess = rand(1, 100) <= $defenseRating;

                if ($tackleSuccess) {
                    $commentary = "Excellent tackle by {$defendingPlayer->name} to win back possession!";

                    $this->recordEvent(
                        'TACKLE',
                        $attackingTeam === 'home' ? 'away' : 'home',
                        $defendingPlayer,
                        $attackingPlayer,
                        $commentary
                    );

                    if (rand(1, 100) <= 15) {
                        $this->recordEvent(
                            'YELLOW_CARD',
                            $attackingTeam === 'home' ? 'away' : 'home',
                            $defendingPlayer,
                            $attackingPlayer,
                            "Yellow card! {$defendingPlayer->name} went in too hard on that tackle against {$attackingPlayer->name}."
                        );
                    }
                } else {
                    $commentary = "{$defendingPlayer->name} attempts a tackle but {$attackingPlayer->name} evades it.";
                    $this->recordEvent(
                        'TACKLE_MISSED',
                        $attackingTeam === 'home' ? 'away' : 'home',
                        $defendingPlayer,
                        $attackingPlayer,
                        $commentary
                    );
                }
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
                $commentary = "Yellow card! {$defendingPlayer->name} makes a reckless challenge on {$attackingPlayer->name}.";

                $this->recordEvent(
                    'YELLOW_CARD',
                    ($attackingTeam === 'home' ? 'away' : 'home'),
                    $defendingPlayer,
                    $attackingPlayer,
                    $commentary
                );
                break;

            case 'GREAT_SAVE':
                $stats = $attackingTeam === 'home' ?
                    $this->homePlayerStats[$attackingPlayer->getKey()] :
                    $this->awayPlayerStats[$attackingPlayer->getKey()];

                $defenderStats = $attackingTeam === 'home' ?
                    $this->awayPlayerStats[$defendingPlayer->getKey()] :
                    $this->homePlayerStats[$defendingPlayer->getKey()];

                $shotPower = $stats['attacking'] * (1 - $this->playerFatigue[$attackingPlayer->getKey()]);
                $saveQuality = $defenderStats['defending'] * (1 - $this->playerFatigue[$defendingPlayer->getKey()]);

                if ($shotPower > $saveQuality + 15) {
                    if ($attackingTeam === 'home') {
                        $this->homeScore++;
                    } else {
                        $this->awayScore++;
                    }

                    $commentary = "GOAL! What a powerful strike from {$attackingPlayer->name}! {$defendingPlayer->name} got a hand to it but couldn't keep it out!";

                    $this->recordEvent(
                        'GOAL',
                        $attackingTeam,
                        $attackingPlayer,
                        null,
                        $commentary
                    );
                } else {
                    $commentary = "What a save! {$defendingPlayer->name} makes a brilliant stop to deny {$attackingPlayer->name}.";

                    $this->recordEvent(
                        'GREAT_SAVE',
                        ($attackingTeam === 'home' ? 'away' : 'home'),
                        $defendingPlayer,
                        $attackingPlayer,
                        $commentary
                    );
                }
                break;

            default:
                $commentary = "{$attackingPlayer->name} makes a move forward for " .
                    ($attackingTeam === 'home' ? $this->homeTeam->name : $this->awayTeam->name) . ".";

                $this->recordEvent(
                    'GENERIC',
                    $attackingTeam,
                    $attackingPlayer,
                    null,
                    $commentary
                );
                break;
        }
    }

    private function calculateSkillMoveSuccess(array $attackerStats, array $defenderStats): bool
    {
        $attackerRating = $attackerStats['technical_skills'] * 0.6 + $attackerStats['speed'] * 0.4;
        $defenderRating = $defenderStats['defending'] * 0.6 + $defenderStats['speed'] * 0.4;

        $attackerRating *= (1 - $this->playerFatigue[$attackerStats['id']]);
        $defenderRating *= (1 - $this->playerFatigue[$defenderStats['id']]);

        $successChance = 50 + ($attackerRating - $defenderRating) / 2;

        $successChance = max(10, min(90, $successChance));

        return rand(1, 100) <= $successChance;
    }

    private function attemptGoal(string $attackingTeam, Player $attacker, Player $defender): bool
    {
        $attackerStats = $attackingTeam === 'home' ?
            $this->homePlayerStats[$attacker->getKey()] :
            $this->awayPlayerStats[$attacker->getKey()];

        $defenderStats = $attackingTeam === 'home' ?
            $this->awayPlayerStats[$defender->getKey()] :
            $this->homePlayerStats[$defender->getKey()];

        $attackRating = ($attackerStats['attacking'] * 0.6) +
            ($attackerStats['technical_skills'] * 0.3) +
            ($attackerStats['speed'] * 0.1);

        $attackRating *= (1 - $this->playerFatigue[$attacker->getKey()]);
        $baseChance = $attackRating / 1.3;
        $defenseRating = $defenderStats['defending'];
        $defenseRating *= (1 - $this->playerFatigue[$defender->getKey()]);
        $defenderImpact = $defenseRating * 0.3;

        $tacticalModifier = 0;
        if ($attackingTeam === 'home') {
            if ($this->homeTeam->current_tactic === 'ATTACK_MODE') {
                $tacticalModifier += 15;
            } elseif ($this->homeTeam->current_tactic === 'DEFEND_MODE') {
                $tacticalModifier -= 10;
            }

            if ($this->awayTeam->current_tactic === 'DEFEND_MODE') {
                $tacticalModifier -= 5;
            }
        } else {
            if ($this->awayTeam->current_tactic === 'ATTACK_MODE') {
                $tacticalModifier += 15;
            } elseif ($this->awayTeam->current_tactic === 'DEFEND_MODE') {
                $tacticalModifier -= 10;
            }

            if ($this->homeTeam->current_tactic === 'DEFEND_MODE') {
                $tacticalModifier -= 5;
            }
        }

        $homeAdvantage = ($attackingTeam === 'home') ? 8 : 0;

        $scoreDifference = ($attackingTeam === 'home') ?
            ($this->awayScore - $this->homeScore) :
            ($this->homeScore - $this->awayScore);

        $comebackModifier = max(0, min(15, $scoreDifference * 5)); // Up to +15% boost when trailing

        $finalChance = max(10, min(85, $baseChance - $defenderImpact + $tacticalModifier + $homeAdvantage + $comebackModifier)); // Increased min from 5 to 10, max from 80 to 85

        if ($this->currentMinute >= 85 || ($this->homeScore == $this->awayScore && $this->currentMinute >= 75)) {
            Log::debug("Goal attempt calculation", [
                'minute' => $this->currentMinute,
                'team' => $attackingTeam,
                'player' => $attacker->name,
                'baseChance' => $baseChance,
                'defenderImpact' => $defenderImpact,
                'tacticalModifier' => $tacticalModifier,
                'homeAdvantage' => $homeAdvantage,
                'comebackModifier' => $comebackModifier,
                'finalChance' => $finalChance
            ]);
        }

        return rand(1, 100) <= $finalChance;
    }

    private function getPlayerForAction(string $team, string $actionType, array $excludeIds = []): ?Player
    {
        $players = ($team === 'home') ? $this->homePlayers : $this->awayPlayers;
        $playerStats = ($team === 'home') ? $this->homePlayerStats : $this->awayPlayerStats;

        $players = $players->whereNotIn('id', $excludeIds);

        if ($players->isEmpty()) {
            return null;
        }

        $positionPreferences = [];
        $statWeights = [];

        switch ($actionType) {
            case 'GOAL':
            case 'SHOT_ON_TARGET':
            case 'SHOT_OFF_TARGET':
                $positionPreferences = ['STRIKER' => 5, 'WINGER' => 3, 'MIDFIELDER' => 2];
                $statWeights = ['attacking' => 0.6, 'technical_skills' => 0.2, 'speed' => 0.1, 'tactical_sense' => 0.1];
                break;

            case 'ASSIST':
                $positionPreferences = ['MIDFIELDER' => 5, 'WINGER' => 4, 'STRIKER' => 2, 'FULLBACK' => 1];
                $statWeights = ['technical_skills' => 0.5, 'tactical_sense' => 0.3, 'speed' => 0.1, 'attacking' => 0.1];
                break;

            case 'CORNER':
            case 'FREE_KICK':
                $positionPreferences = ['MIDFIELDER' => 5, 'WINGER' => 3, 'FULLBACK' => 2];
                $statWeights = ['technical_skills' => 0.8, 'tactical_sense' => 0.2];
                break;

            case 'SKILL_MOVE':
                $positionPreferences = ['WINGER' => 5, 'STRIKER' => 3, 'MIDFIELDER' => 2];
                $statWeights = ['technical_skills' => 0.5, 'speed' => 0.3, 'attacking' => 0.2];
                break;

            default:
                $positionPreferences = [
                    'STRIKER' => 1,
                    'WINGER' => 1,
                    'MIDFIELDER' => 1,
                    'FULLBACK' => 1,
                    'CENTRE_BACK' => 1,
                    'GOALKEEPER' => 0.1
                ];
                $statWeights = [
                    'attacking' => 0.2,
                    'defending' => 0.2,
                    'stamina' => 0.2,
                    'technical_skills' => 0.2,
                    'speed' => 0.1,
                    'tactical_sense' => 0.1
                ];
                break;
        }

        $playerRatings = [];

        foreach ($players as $player) {
            $stats = $playerStats[$player->getKey()] ?? null;
            if (!$stats) continue;

            $positionMultiplier = $positionPreferences[$player->position] ?? 0.5;

            $weightedRating = 0;
            foreach ($statWeights as $stat => $weight) {
                $weightedRating += ($stats[$stat] ?? 50) * $weight;
            }

            $weightedRating *= $positionMultiplier;

            $conditionFactor = $stats['condition'] / 100;
            $fatigueFactor = 1 - $this->playerFatigue[$player->getKey()];

            $weightedRating *= $conditionFactor * $fatigueFactor;

            $playerRatings[$player->getKey()] = $weightedRating;
        }

        $totalRating = array_sum($playerRatings);
        if ($totalRating <= 0) {
            return $players->random();
        }

        $randValue = rand(0, (int)($totalRating * 100)) / 100;
        $cumulativeRating = 0;

        foreach ($playerRatings as $playerId => $rating) {
            $cumulativeRating += $rating;
            if ($cumulativeRating >= $randValue) {
                return $players->firstWhere('id', $playerId);
            }
        }

        return $players->first();
    }

    private function getDefendingPlayer(string $team, string $actionType): ?Player
    {
        $players = ($team === 'home') ? $this->homePlayers : $this->awayPlayers;
        $playerStats = ($team === 'home') ? $this->homePlayerStats : $this->awayPlayerStats;

        if ($players->isEmpty()) {
            return null;
        }

        $positionPreferences = [];

        switch ($actionType) {
            case 'GOAL':
            case 'SHOT_ON_TARGET':
            case 'SHOT_OFF_TARGET':
            case 'GREAT_SAVE':
                $positionPreferences = ['GOALKEEPER' => 10];
                break;

            case 'SKILL_MOVE':
            case 'TACKLE':
                $positionPreferences = [
                    'CENTRE_BACK' => 3,
                    'FULLBACK' => 3,
                    'MIDFIELDER' => 2,
                    'WINGER' => 0.5,
                    'STRIKER' => 0.2,
                    'GOALKEEPER' => 0.1
                ];
                break;

            default:
                $positionPreferences = [
                    'CENTRE_BACK' => 3,
                    'FULLBACK' => 2,
                    'MIDFIELDER' => 1.5,
                    'WINGER' => 0.5,
                    'STRIKER' => 0.3,
                    'GOALKEEPER' => 1
                ];
                break;
        }

        $playerRatings = [];

        foreach ($players as $player) {
            $stats = $playerStats[$player->getKey()] ?? null;
            if (!$stats) continue;

            $positionMultiplier = $positionPreferences[$player->position] ?? 0.5;

            $defensiveRating = 0;

            if ($player->position === 'GOALKEEPER' && in_array($actionType, ['GOAL', 'SHOT_ON_TARGET', 'GREAT_SAVE'])) {
                $defensiveRating = $stats['defending'] * 1.5;
            } else {
                $defensiveRating = ($stats['defending'] * 0.6) +
                    ($stats['tactical_sense'] * 0.2) +
                    ($stats['speed'] * 0.2);
            }

            $defensiveRating *= $positionMultiplier;

            $conditionFactor = $stats['condition'] / 100;
            $fatigueFactor = 1 - $this->playerFatigue[$player->getKey()];

            $defensiveRating *= $conditionFactor * $fatigueFactor;

            $playerRatings[$player->getKey()] = $defensiveRating;
        }

        $totalRating = array_sum($playerRatings);
        if ($totalRating <= 0) {
            return $players->random();
        }

        $randValue = rand(0, (int)($totalRating * 100)) / 100;
        $cumulativeRating = 0;

        foreach ($playerRatings as $playerId => $rating) {
            $cumulativeRating += $rating;
            if ($cumulativeRating >= $randValue) {
                return $players->firstWhere('id', $playerId);
            }
        }

        return $players->first();
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

    private function getRandomPlayer(Team $team, array $positions = [], array $excludeIds = []): ?Player
    {
        $query = Player::where('team_id', $team->getKey())
            ->where('is_injured', false);

        if (!empty($positions)) {
            $query->whereIn('position', $positions);
        }

        if (!empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        $players = $query->get();

        if ($players->isEmpty()) {
            return Player::where('team_id', $team->getKey())
                ->whereNotIn('id', $excludeIds)
                ->first();
        }

        $totalRating = $players->sum('rating');
        $rand = mt_rand(1, $totalRating);

        $currentRating = 0;
        foreach ($players as $player) {
            $currentRating += $player->rating;
            if ($rand <= $currentRating) {
                return $player;
            }
        }

        return $players->first();
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

    private function generateSavedShotCommentary(Player $shooter, Player $goalkeeper): string
    {
        $templates = [
            "Close! {shooter} with a great effort but {goalkeeper} makes the save!",
            "Good chance for {team}! {shooter} shoots but {goalkeeper} is equal to it!",
            "{shooter} tries his luck from distance but {goalkeeper} makes a comfortable save!",
            "What a save from {goalkeeper}! {shooter} will be wondering how that didn't go in!",
        ];

        $template = $templates[array_rand($templates)];
        $team = ($shooter->team_id === $this->homeTeam->getKey()) ? $this->homeTeam->name : $this->awayTeam->name;

        return strtr($template, [
            '{shooter}' => $shooter->name,
            '{goalkeeper}' => $goalkeeper->name,
            '{team}' => $team,
        ]);
    }

    private function generateShotOnTargetCommentary(Player $shooter, Player $goalkeeper): string
    {
        $templates = [
            "{shooter} gets a shot on target but it's saved by {goalkeeper}.",
            "Good effort! {shooter} tests {goalkeeper} with a powerful shot.",
            "Shot from {shooter}! Straight at {goalkeeper} though.",
            "{team} on the attack, {shooter} gets a shot away but it's gathered by {goalkeeper}.",
        ];

        $template = $templates[array_rand($templates)];
        $team = ($shooter->team_id === $this->homeTeam->getKey()) ? $this->homeTeam->name : $this->awayTeam->name;

        return strtr($template, [
            '{shooter}' => $shooter->name,
            '{goalkeeper}' => $goalkeeper->name,
            '{team}' => $team,
        ]);
    }

    private function generateShotOffTargetCommentary(Player $shooter): string
    {
        $templates = [
            "Shot by {shooter} but it's well wide of the target!",
            "{shooter} tries to curl one but it's off target!",
            "Good chance for {team} but {shooter} sends it high over the bar!",
            "{shooter} with the shot... but it's not troubling the goalkeeper.",
        ];

        $template = $templates[array_rand($templates)];
        $team = ($shooter->team_id === $this->homeTeam->getKey()) ? $this->homeTeam->name : $this->awayTeam->name;

        return strtr($template, [
            '{shooter}' => $shooter->name,
            '{team}' => $team,
        ]);
    }
}
