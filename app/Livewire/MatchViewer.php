<?php

namespace App\Livewire;

use App\Models\MatchModel;
use App\Models\Team;
use App\Services\RealtimeMatchSimulationService;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Match Viewer')]
class MatchViewer extends Component
{
    public $status;
    public $match;
    public $homeTeam;
    public $awayTeam;
    public $isUserTeam = false;
    public $matchEvents = [];
    public $matchStats = [
        'current_minute' => 0,
        'home_score' => 0,
        'away_score' => 0,
        'home_possession' => 50,
        'away_possession' => 50,
        'home_shots' => 0,
        'away_shots' => 0,
        'home_shots_on_target' => 0,
        'away_shots_on_target' => 0,
    ];
    public $isMatchLive = false;
    public $canStartMatch = false;
    public $isLoading = false;

    public function getMatchEvents(): void
    {
        $this->dispatch('match_updated', [
            'events' => $this->matchEvents
        ]);
    }

    private function loadMatchEvents(): void
    {
        $events = $this->match->events()->orderBy('minute', 'asc')->get();

        if ($events->isNotEmpty()) {
            $this->matchEvents = $events->toArray();
            return;
        }

        $this->generateEventsFromPerformances();
    }

    private function generateEventsFromPerformances(): void
    {
        $performances = $this->match->playerPerformances()
            ->with('player')
            ->get();

        $events = [];
        $homeGoals = 0;
        $awayGoals = 0;

        foreach ($performances as $performance) {
            if (!$performance->player) continue;

            $player = $performance->player;
            $isHomeTeam = ($player->team_id == $this->homeTeam->getKey());
            $team = $isHomeTeam ? 'home' : 'away';

            for ($i = 0; $i < $performance->goals_scored; $i++) {
                $minute = $this->generateRandomMinute($i, $performance->goals_scored);

                $assister = null;
                if (rand(1, 100) <= 70) {
                    $potentialAssister = $performances
                        ->where('player.team_id', $player->team_id)
                        ->where('player_id', '!=', $player->getKey())
                        ->where('assists', '>', 0)
                        ->first();

                    if ($potentialAssister) {
                        $assister = $potentialAssister->player;
                    }
                }

                if ($isHomeTeam) {
                    $homeGoals++;
                } else {
                    $awayGoals++;
                }

                $events[] = [
                    'type' => 'GOAL',
                    'minute' => $minute,
                    'team' => $team,
                    'main_player_id' => $player->getKey(),
                    'main_player_name' => $player->name,
                    'secondary_player_id' => $assister ? $assister->getKey() : null,
                    'secondary_player_name' => $assister ? $assister->name : null,
                    'commentary' => "GOAL! " . $player->name . " scores" .
                        ($assister ? " after a great assist from " . $assister->name : "") . "!",
                    'home_score' => $isHomeTeam ? $homeGoals : $homeGoals,
                    'away_score' => $isHomeTeam ? $awayGoals : $awayGoals,
                ];

                $numShots = rand(1, 3);
                for ($j = 0; $j < $numShots; $j++) {
                    $shotMinute = $this->generateRandomMinute($j, $numShots);
                    if ($shotMinute == $minute) continue;

                    $events[] = [
                        'type' => 'SHOT',
                        'minute' => $shotMinute,
                        'team' => $team,
                        'main_player_id' => $player->getKey(),
                        'main_player_name' => $player->name,
                        'commentary' => "{$player->name} takes a shot.",
                        'home_score' => $isHomeTeam ? $homeGoals : $homeGoals,
                        'away_score' => $isHomeTeam ? $awayGoals : $awayGoals,
                    ];
                }
            }
        }

        if (empty($events) && ($this->match->home_team_score > 0 || $this->match->away_team_score > 0)) {
            $this->generateGenericEvents();
            return;
        }

        usort($events, function($a, $b) {
            return $a['minute'] <=> $b['minute'];
        });

        $this->matchEvents = $events;
    }

    private function generateRandomMinute($goalIndex, $totalGoals)
    {
        $avgMinutesPerGoal = 85 / ($totalGoals + 1);
        $baseMinute = ($goalIndex + 1) * $avgMinutesPerGoal;

        $variance = rand(-10, 10);
        $minute = round($baseMinute + $variance);

        return max(1, min(90, $minute));
    }

    private function generateGenericEvents(): void
    {
        $events = [];

        for ($i = 0; $i < $this->match->home_team_score; $i++) {
            $minute = $this->generateRandomMinute($i, $this->match->home_team_score);
            $events[] = [
                'type' => 'GOAL',
                'minute' => $minute,
                'team' => 'home',
                'main_player_name' => 'Unknown Player',
                'commentary' => "GOAL! The home team scores!",
                'home_score' => $i + 1,
                'away_score' => 0,
            ];
        }

        for ($i = 0; $i < $this->match->away_team_score; $i++) {
            $minute = $this->generateRandomMinute($i, $this->match->away_team_score);
            $events[] = [
                'type' => 'GOAL',
                'minute' => $minute,
                'team' => 'away',
                'main_player_name' => 'Unknown Player',
                'commentary' => "GOAL! The away team scores!",
                'home_score' => $this->match->home_team_score,
                'away_score' => $i + 1,
            ];
        }

        usort($events, function($a, $b) {
            return $a['minute'] <=> $b['minute'];
        });

        $this->matchEvents = $events;
    }

    public function getListeners()
    {
        $listeners = [
            'refreshMatchData' => 'refreshMatchData',
            'getMatchEvents' => 'getMatchEvents'
        ];

        if ($this->match && $this->match->getKey()) {
            $listeners["echo-presence:match.{$this->match->getKey()},MatchStatusUpdate"] = 'handleMatchUpdate';
        }

        return $listeners;
    }

    public function mount($matchId): void
    {
        $this->match = MatchModel::findOrFail($matchId);
        $this->homeTeam = Team::findOrFail($this->match->home_team_id);
        $this->awayTeam = Team::findOrFail($this->match->away_team_id);

        $this->getListeners = function() {
            return [
                "echo-presence:match.{$this->match->id},MatchStatusUpdate" => 'handleMatchUpdate',
                'refreshMatchData' => 'refreshMatchData',
                'getMatchEvents' => 'getMatchEvents'
            ];
        };

        $userTeamId = Auth::user()->currentTeam->getKey();
        $this->isUserTeam = ($userTeamId == $this->homeTeam->getKey() || $userTeamId == $this->awayTeam->getKey());

        if (!$this->isUserTeam) {
            $this->redirect('/dashboard');
        }

        $simulationService = app(RealtimeMatchSimulationService::class);
        $status = $simulationService->getMatchStatus($this->match);
        $this->isMatchLive = (strtoupper($status) === 'IN_PROGRESS');
        $this->status = $status;

        $this->setInitialMatchStats();

        if ($this->status === 'IN_PROGRESS' || $this->status === 'COMPLETED' ||
            $this->match->home_team_score > 0 || $this->match->away_team_score > 0) {
            $this->loadMatchEvents();
        }

        $this->canStartMatch = $this->determineCanStartMatch();
    }

    private function setInitialMatchStats(): void
    {
        $this->matchStats = [
            'current_minute' => 0,
            'home_score' => $this->match->home_team_score ?? 0,
            'away_score' => $this->match->away_team_score ?? 0,
            'home_possession' => $this->match->home_possession ?? 50,
            'away_possession' => $this->match->away_possession ?? 50,
            'home_shots' => $this->match->home_shots ?? 0,
            'away_shots' => $this->match->away_shots ?? 0,
            'home_shots_on_target' => $this->match->home_shots_on_target ?? 0,
            'away_shots_on_target' => $this->match->away_shots_on_target ?? 0,
        ];

        if ($this->isMatchLive) {
            $simulationService = app(RealtimeMatchSimulationService::class);
            $matchState = $simulationService->getMatchState($this->match);

            if (isset($matchState['current_minute'])) {
                $this->matchStats['current_minute'] = $matchState['current_minute'];
            }

            if (isset($matchState['home_team'])) {
                $this->updateStatsFromMatchState($matchState);
            }
        }
    }

    private function updateStatsFromMatchState($matchState): void
    {
        $stats = [
            'possession' => 'possession',
            'shots' => 'shots',
            'shots_on_target' => 'shots_on_target',
            'score' => 'score'
        ];

        foreach ($stats as $statKey => $matchStatsKey) {
            if (isset($matchState['home_team'][$statKey])) {
                $this->matchStats['home_' . $matchStatsKey] = $matchState['home_team'][$statKey];
                $this->matchStats['away_' . $matchStatsKey] = $matchState['away_team'][$statKey];
            }
        }
    }

    private function determineCanStartMatch(): bool
    {
        return $this->isUserTeam &&
            $this->status === 'pending' &&
            now()->addMinutes(15)->gte($this->match->match_date) &&
            $this->match->home_team_score == 0 &&
            $this->match->away_team_score == 0;
    }

    public function handleMatchUpdate($update): void
    {
        if (!isset($update['current_minute']) ||
            !isset($update['home_team']) ||
            !isset($update['away_team'])) {
            return;
        }

        $this->matchStats['current_minute'] = $update['current_minute'];
        $this->matchStats['home_score'] = $update['home_team']['score'];
        $this->matchStats['away_score'] = $update['away_team']['score'];

        if (isset($update['home_team']['possession'])) {
            $this->matchStats['home_possession'] = $update['home_team']['possession'];
            $this->matchStats['away_possession'] = $update['away_team']['possession'];
        }

        if (isset($update['home_team']['shots'])) {
            $this->matchStats['home_shots'] = $update['home_team']['shots'];
            $this->matchStats['away_shots'] = $update['away_team']['shots'];
        }

        if (isset($update['home_team']['shots_on_target'])) {
            $this->matchStats['home_shots_on_target'] = $update['home_team']['shots_on_target'];
            $this->matchStats['away_shots_on_target'] = $update['away_team']['shots_on_target'];
        }

        if (isset($update['event']) && $update['event']) {
            $this->processNewEvent($update['event']);
        }

        if (isset($update['type'])) {
            $this->handleMatchStatusChange($update);
        }
    }

    private function processNewEvent($event): void
    {
        $eventSignature = $event['type'] . '-' . $event['minute'] . '-' . ($event['main_player_id'] ?? '');

        foreach ($this->matchEvents as $existingEvent) {
            $existingSignature = $existingEvent['type'] . '-' . $existingEvent['minute'] . '-' .
                ($existingEvent['main_player_id'] ?? '');

            if ($existingSignature === $eventSignature) {
                return;
            }
        }

        $this->matchEvents[] = $event;
        usort($this->matchEvents, function($a, $b) {
            return $a['minute'] <=> $b['minute'];
        });

        $this->dispatch('match_updated', ['events' => $this->matchEvents]);
    }

    private function handleMatchStatusChange($update): void
    {
        $simulationService = app(RealtimeMatchSimulationService::class);

        if ($update['type'] === 'MATCH_START') {
            $this->isMatchLive = true;
            $this->status = 'IN_PROGRESS';
            $this->dispatch('status_updated', [
                'isLive' => true,
                'status' => 'IN_PROGRESS'
            ]);
        }
        else if ($update['type'] === 'MATCH_END' || $update['type'] === 'FINAL_STATS_CONFIRMATION') {
            $this->isMatchLive = false;
            $this->status = 'COMPLETED';

            $simulationService->updateMatchStatus($this->match, 'COMPLETED', 90);

            $this->match->update([
                'home_team_score' => $update['home_team']['score'],
                'away_team_score' => $update['away_team']['score'],
            ]);

            $this->dispatch('status_updated', [
                'isLive' => false,
                'status' => 'COMPLETED'
            ]);
        }
    }

    public function refreshMatchData()
    {
        try {
            $simulationService = app(RealtimeMatchSimulationService::class);
            $this->match->refresh();
            $matchState = $simulationService->getMatchState($this->match);

            if ($matchState) {
                if (isset($matchState['current_minute'])) {
                    $this->matchStats['current_minute'] = $matchState['current_minute'];
                }

                if (isset($matchState['home_team'])) {
                    $this->updateStatsFromMatchState($matchState);
                }
            }

            $this->loadMatchEvents();
            $this->getMatchEvents();
            $status = $simulationService->getMatchStatus($this->match);
            $this->isMatchLive = (strtoupper($status) === 'IN_PROGRESS');
            $this->status = $status;

            $this->dispatch('success', "Match data refreshed");
        } catch (Exception $e) {
            $this->dispatch('error', "Failed to refresh data: " . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.match-viewer');
    }
}
