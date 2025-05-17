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
        'GOAL' => 15,
        'SHOT' => 35,
        'CARD' => 10,
        'SAVE' => 15,
        'OTHER' => 25
    ];

    const MATCH_SPEED = 1;
    const SIMULATION_CHUNK = 15;

    protected $match;
    protected $homeTeam;
    protected $awayTeam;
    protected $homePlayers = [];
    protected $awayPlayers = [];
    protected $currentMinute = 0;
    protected $homeScore = 0;
    protected $awayScore = 0;
    protected $homePossession = 50;
    protected $awayPossession = 50;
    protected $homeShots = 0;
    protected $awayShots = 0;
    protected $matchEvents = [];
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
                ->latest()
                ->first();

            $startMinute = ($simulationStatus && $simulationStatus->current_minute > 0)
                ? $simulationStatus->current_minute
                : 0;

            if ($startMinute == 0) {
                $simulationService->updateMatchStatus($this->match, 'IN_PROGRESS', 0);
                $this->initializeSimulation();
                $this->broadcastMatchStart();
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
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function initializeSimulation(): void
    {
        $this->homeTeam = Team::findOrFail($this->match->home_team_id);
        $this->awayTeam = Team::findOrFail($this->match->away_team_id);
        $this->loadPlayers();

        if ($this->currentMinute == 0) {
            $this->homeScore = 0;
            $this->awayScore = 0;
            $this->calculatePossession();
            $this->homeShots = 0;
            $this->awayShots = 0;
            $this->matchEvents = [];
        }
    }

    private function loadPlayers(): void
    {
        $homeLineup = TeamLineup::where('team_id', $this->homeTeam->getKey())
            ->latest()
            ->first();

        $awayLineup = TeamLineup::where('team_id', $this->awayTeam->getKey())
            ->latest()
            ->first();

        if (!$homeLineup || !$awayLineup) {
            throw new Exception("Missing lineup data for match simulation");
        }

        $homeStarterIds = LineupPlayer::where('lineup_id', $homeLineup->getKey())
            ->where('is_starter', 1)
            ->pluck('player_id');

        $awayStarterIds = LineupPlayer::where('lineup_id', $awayLineup->getKey())
            ->where('is_starter', 1)
            ->pluck('player_id');

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
                ->limit(11 - $this->homePlayers->count())
                ->get();

            $this->homePlayers = $this->homePlayers->merge($additionalHomePlayers);
        }

        if ($this->awayPlayers->count() < 11) {
            $additionalAwayPlayers = Player::where('team_id', $this->awayTeam->getKey())
                ->where('is_injured', false)
                ->whereNotIn('id', $awayStarterIds)
                ->limit(11 - $this->awayPlayers->count())
                ->get();

            $this->awayPlayers = $this->awayPlayers->merge($additionalAwayPlayers);
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
            } else if ($event->type === 'SHOT') {
                if ($event->team === 'home') {
                    $this->homeShots++;
                } else {
                    $this->awayShots++;
                }
            }
        }
    }

    private function calculatePossession(): void
    {
        $homeTeamRating = $this->homeTeam->rating ?? 70;
        $awayTeamRating = $this->awayTeam->rating ?? 70;

        $homeTeamRating *= 1.1;

        if ($this->homeTeam->current_tactic === 'ATTACK_MODE') {
            $homeTeamRating += 5;
        } elseif ($this->homeTeam->current_tactic === 'DEFEND_MODE') {
            $homeTeamRating -= 5;
        }

        if ($this->awayTeam->current_tactic === 'ATTACK_MODE') {
            $awayTeamRating += 5;
        } elseif ($this->awayTeam->current_tactic === 'DEFEND_MODE') {
            $awayTeamRating -= 5;
        }

        $totalRating = $homeTeamRating + $awayTeamRating;
        $this->homePossession = round(($homeTeamRating / $totalRating) * 100);

        $this->homePossession = max(30, min(70, $this->homePossession));
        $this->awayPossession = 100 - $this->homePossession;
    }

    private function simulateMinute(): void
    {
        $this->updatePossession();
        $this->broadcastMatchUpdate('TIME_UPDATE', null, null);
        usleep(100000);
        $eventChance = 30;

        if ($this->currentMinute >= 43 && $this->currentMinute <= 47) {
            $eventChance = 35;
        } else if ($this->currentMinute >= 85) {
            $eventChance = 35;
        }

        if ($this->currentMinute >= 80 && $this->homeScore == $this->awayScore) {
            $eventChance += 10;
        }

        $attackingTeam = (rand(1, 100) <= $this->homePossession) ? 'home' : 'away';

        if (rand(1, 100) <= $eventChance) {
            $this->generateEvent($attackingTeam);
        }
    }

    private function updatePossession(): void
    {
        $possessionChange = rand(-2, 2);

        $scoreDiff = $this->homeScore - $this->awayScore;
        if ($scoreDiff != 0) {
            $possessionChange += ($scoreDiff > 0) ? -1 : 1;
        }

        $this->homePossession += $possessionChange;
        $this->homePossession = max(30, min(70, $this->homePossession));
        $this->awayPossession = 100 - $this->homePossession;
    }

    private function generateEvent(string $attackingTeam): void
    {
        $eventType = $this->selectRandomEvent();
        $attackingPlayer = $this->getRandomPlayer($attackingTeam, $eventType);
        $defendingPlayer = $this->getRandomPlayer(($attackingTeam === 'home') ? 'away' : 'home');

        if (!$attackingPlayer || !$defendingPlayer) {
            return;
        }

        switch ($eventType) {
            case 'GOAL':
                $scoreChance = 45;

                $attackerRating = $attackingPlayer->rating ?? 70;
                $defenderRating = $defendingPlayer->rating ?? 70;
                $ratingDiff = $attackerRating - $defenderRating;
                $scoreChance += $ratingDiff / 4;

                if ($attackingTeam === 'home') {
                    $scoreChance += 5;
                }

                if (($attackingTeam === 'home' && $this->homeScore < $this->awayScore) ||
                    ($attackingTeam === 'away' && $this->awayScore < $this->homeScore)) {
                    $scoreChance += 5;
                }

                $scoreChance = max(25, min(75, $scoreChance));

                if (rand(1, 100) <= $scoreChance) {
                    if ($attackingTeam === 'home') {
                        $this->homeScore++;
                    } else {
                        $this->awayScore++;
                    }

                    $commentary = "GOAL! {$attackingPlayer->name} scores for " .
                        ($attackingTeam === 'home' ? $this->homeTeam->name : $this->awayTeam->name) .
                        "! The score is now {$this->homeScore}-{$this->awayScore}!";

                    $this->recordEvent('GOAL', $attackingTeam, $attackingPlayer, null, $commentary);
                } else {
                    $commentary = "Shot by {$attackingPlayer->name}, but it's saved by {$defendingPlayer->name}.";
                    $this->recordEvent('SAVE', $attackingTeam === 'home' ? 'away' : 'home', $defendingPlayer, $attackingPlayer, $commentary);
                }
                break;

            case 'SHOT':
                if ($attackingTeam === 'home') {
                    $this->homeShots++;
                } else {
                    $this->awayShots++;
                }

                $commentary = "{$attackingPlayer->name} takes a shot, but it goes wide.";
                $this->recordEvent('SHOT', $attackingTeam, $attackingPlayer, null, $commentary);
                break;

            case 'CARD':
                $isRed = rand(1, 10) === 1;
                $cardType = $isRed ? 'RED_CARD' : 'YELLOW_CARD';

                $commentary = ($isRed ? "RED CARD! " : "Yellow card! ") .
                    "{$attackingPlayer->name} commits a foul on {$defendingPlayer->name}.";

                $this->recordEvent($cardType, $attackingTeam, $attackingPlayer, $defendingPlayer, $commentary);
                break;

            case 'SAVE':
                $defTeam = $attackingTeam === 'home' ? 'away' : 'home';
                $commentary = "Great save by {$defendingPlayer->name} to deny {$attackingPlayer->name}!";
                $this->recordEvent('SAVE', $defTeam, $defendingPlayer, $attackingPlayer, $commentary);
                break;

            case 'OTHER':
                $otherEvents = ['CORNER', 'TACKLE', 'FREE_KICK'];
                $specificEvent = $otherEvents[array_rand($otherEvents)];

                $commentary = $this->getGenericCommentary($specificEvent, $attackingPlayer, $defendingPlayer);
                $this->recordEvent($specificEvent, $attackingTeam, $attackingPlayer, $defendingPlayer, $commentary);
                break;
        }
    }

    private function getRandomPlayer(string $team, string $eventType = null): ?Player
    {
        $players = ($team === 'home') ? $this->homePlayers : $this->awayPlayers;

        if ($players->isEmpty()) {
            return null;
        }

        if ($eventType === 'GOAL') {
            $attackers = $players->filter(function ($player) {
                return in_array($player->position, ['STRIKER', 'WINGER']);
            });

            if ($attackers->isNotEmpty() && rand(1, 100) <= 70) {
                return $attackers->random();
            }
        } elseif ($eventType === 'SAVE') {
            $goalkeeper = $players->firstWhere('position', 'GOALKEEPER');
            if ($goalkeeper) {
                return $goalkeeper;
            }
        }

        return $players->random();
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

    private function getGenericCommentary(string $eventType, Player $player1, Player $player2): string
    {
        return match ($eventType) {
            'CORNER' => "Corner kick taken by {$player1->name}.",
            'TACKLE' => "{$player1->name} makes a clean tackle on {$player2->name}.",
            'FREE_KICK' => "Free kick awarded to {$player1->team->name}, to be taken by {$player1->name}.",
            default => "{$player1->name} is involved in the action.",
        };
    }

    private function recordEvent(string $type, string $team, ?Player $mainPlayer, ?Player $secondaryPlayer, string $commentary): void
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

    private function broadcastMatchStart(): void
    {
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
            ],
            'away_team' => [
                'id' => $this->awayTeam->getKey(),
                'name' => $this->awayTeam->name,
                'score' => 0,
                'possession' => $this->awayPossession,
                'shots' => 0,
            ],
            'commentary' => "The match between {$this->homeTeam->name} and {$this->awayTeam->name} is about to begin!",
        ];

        broadcast(new MatchStatusUpdate($payload));
        sleep(1);
    }

    private function broadcastMatchUpdate(string $updateType, ?array $event, ?string $commentary): void
    {
        $payload = [
            'match_id' => $this->match->getKey(),
            'type' => $updateType,
            'current_minute' => $this->currentMinute,
            'home_team' => [
                'id' => $this->homeTeam->getKey(),
                'name' => $this->homeTeam->name,
                'score' => $this->homeScore,
                'possession' => $this->homePossession,
                'shots' => $this->homeShots,
            ],
            'away_team' => [
                'id' => $this->awayTeam->getKey(),
                'name' => $this->awayTeam->name,
                'score' => $this->awayScore,
                'possession' => $this->awayPossession,
                'shots' => $this->awayShots,
            ],
            'event' => $event,
            'commentary' => $commentary,
        ];

        broadcast(new MatchStatusUpdate($payload));
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
        ]);

        $this->recordPlayerPerformances();
        $this->updateStandings();
        $simulationService->updateMatchStatus($this->match, 'COMPLETED', 90);

        $this->broadcastMatchUpdate(
            'MATCH_END',
            null,
            "Full time! The match ends {$this->homeTeam->name} {$this->homeScore} - {$this->awayScore} {$this->awayTeam->name}"
        );
    }

    private function recordPlayerPerformances(): void
    {
        $events = MatchEvent::where('match_id', $this->match->getKey())->get();
        $playerStats = [];

        foreach ($this->homePlayers as $player) {
            $playerStats[$player->getKey()] = [
                'goals' => 0,
                'assists' => 0,
                'rating' => 60,
            ];
        }

        foreach ($this->awayPlayers as $player) {
            $playerStats[$player->getKey()] = [
                'goals' => 0,
                'assists' => 0,
                'rating' => 60,
            ];
        }

        foreach ($events as $event) {
            $mainPlayerId = $event->main_player_id;
            $secondaryPlayerId = $event->secondary_player_id;

            if ($mainPlayerId && isset($playerStats[$mainPlayerId])) {
                switch ($event->type) {
                    case 'GOAL':
                        $playerStats[$mainPlayerId]['goals']++;
                        $playerStats[$mainPlayerId]['rating'] += 15;

                        if ($secondaryPlayerId && isset($playerStats[$secondaryPlayerId])) {
                            $playerStats[$secondaryPlayerId]['assists']++;
                            $playerStats[$secondaryPlayerId]['rating'] += 10;
                        }
                        break;

                    case 'SHOT':
                        $playerStats[$mainPlayerId]['rating'] += 1;
                        break;

                    case 'SAVE':
                        $playerStats[$mainPlayerId]['rating'] += 5;
                        break;

                    case 'YELLOW_CARD':
                        $playerStats[$mainPlayerId]['rating'] -= 5;
                        break;

                    case 'RED_CARD':
                        $playerStats[$mainPlayerId]['rating'] -= 15;
                        break;
                }
            }
        }

        $isHomeWin = $this->homeScore > $this->awayScore;
        $isAwayWin = $this->awayScore > $this->homeScore;
        $isDraw = $this->homeScore == $this->awayScore;

        foreach ($playerStats as $playerId => $stats) {
            $player = Player::find($playerId);
            if (!$player) continue;

            $isHomeTeam = $player->team_id == $this->homeTeam->getKey();

            if (($isHomeTeam && $isHomeWin) || (!$isHomeTeam && $isAwayWin)) {
                $stats['rating'] += 5;
            } elseif ($isDraw) {
                $stats['rating'] += 2;
            }

            $finalRating = max(1, min(99, $stats['rating']));
            $player->condition = max(10, $player->condition - rand(5, 15));
            $player->save();

            PlayerPerformance::create([
                'player_id' => $playerId,
                'match_id' => $this->match->getKey(),
                'goals_scored' => $stats['goals'],
                'assists' => $stats['assists'],
                'rating' => $finalRating,
                'minutes_played' => 90,
            ]);
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
}
