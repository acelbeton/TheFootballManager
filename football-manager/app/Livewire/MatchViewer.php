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
    public $viewers = [];
    public $viewerCount = 0;

    protected $listeners = [
        'echo-presence:match.{matchId},here' => 'handleViewersPresent',
        'echo-presence:match.{matchId},joining' => 'handleNewViewer',
        'echo-presence:match.{matchId},leaving' => 'handleViewerLeaving',
        'echo-presence:match.{matchId},MatchStatusUpdate' => 'handleMatchUpdate',
        'refreshMatchData' => 'handleMatchUpdate'
    ];

    public function getMatchIdProperty()
    {
        return $this->match->getKey();
    }

    private function loadMatchEvents()
    {
        $performances = $this->match->playerPerformances()
            ->with('player')
            ->get();

        $homeGoals = [];
        $awayGoals = [];

        foreach ($performances as $performance) {
            $player = $performance->player;
            if (!$player) continue;

            $isHomeTeam = ($player->team_id == $this->homeTeam->getKey());
            $team = $isHomeTeam ? 'home' : 'away';

            for ($i = 0; $i < $performance->goals_scored; $i++) {
                $minute = $this->calculateProbableMinuteForGoal($i, $performance->goals_scored);

                $assisterPerformance = $performances
                    ->where('assists', '>', 0)
                    ->where('player.team_id', $player->team_id)
                    ->where('player_id', '!=', $player->getKey())
                    ->first();

                $assister = $assisterPerformance ? $assisterPerformance->player : null;

                $goalEvent = [
                    'type' => 'GOAL',
                    'minute' => $minute,
                    'team' => $team,
                    'main_player_id' => $player->getKey(),
                    'main_player_name' => $player->name,
                    'secondary_player_id' => $assister ? $assister->getKey() : null,
                    'secondary_player_name' => $assister ? $assister->name : null,
                    'commentary' => "GOAL! " . $player->name . " scores" .
                        ($assister ? " after a great assist from " . $assister->name : "") . "!",
                    'home_score' => $isHomeTeam ? count($homeGoals) + 1 : count($homeGoals),
                    'away_score' => $isHomeTeam ? count($awayGoals) : count($awayGoals) + 1,
                ];

                if ($isHomeTeam) {
                    $homeGoals[] = $goalEvent;
                } else {
                    $awayGoals[] = $goalEvent;
                }
            }
        }

        $this->matchEvents = array_merge($homeGoals, $awayGoals);

        usort($this->matchEvents, function($a, $b) {
            return $a['minute'] <=> $b['minute'];
        });

        if (empty($this->matchEvents) && ($this->match->home_team_score > 0 || $this->match->away_team_score > 0)) {
            $this->generateGenericMatchEvents();
        }
    }

    private function calculateProbableMinuteForGoal($goalNumber, $totalGoals)
    {
        if ($totalGoals <= 1) {
            return mt_rand(1, 100) <= 65 ? mt_rand(46, 90) : mt_rand(1, 45);
        }

        $avgSpacing = 90 / ($totalGoals + 1);
        $targetMinute = ($goalNumber + 1) * $avgSpacing;

        $variance = mt_rand(-15, 15);
        return max(1, min(90, (int)($targetMinute + $variance)));
    }

    private function generateGenericMatchEvents()
    {
        for ($i = 0; $i < $this->match->home_team_score; $i++) {
            $minute = $this->calculateProbableMinuteForGoal($i, $this->match->home_team_score);
            $this->matchEvents[] = [
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
            $minute = $this->calculateProbableMinuteForGoal($i, $this->match->away_team_score);
            $this->matchEvents[] = [
                'type' => 'GOAL',
                'minute' => $minute,
                'team' => 'away',
                'main_player_name' => 'Unknown Player',
                'commentary' => "GOAL! The away team scores!",
                'home_score' => $this->match->home_team_score,
                'away_score' => $i + 1,
            ];
        }

        usort($this->matchEvents, function($a, $b) {
            return $a['minute'] <=> $b['minute'];
        });
    }

    public function mount($matchId)
    {
        $this->match = MatchModel::findOrFail($matchId);
        $this->homeTeam = Team::findOrFail($this->match->home_team_id);
        $this->awayTeam = Team::findOrFail($this->match->away_team_id);

        $userTeamId = Auth::user()->currentTeam->getKey();
        $this->isUserTeam = ($userTeamId == $this->homeTeam->getKey() || $userTeamId == $this->awayTeam->getKey());

        $simulationService = app(RealtimeMatchSimulationService::class);
        $status = $simulationService->getMatchStatus($this->match);

        $this->isMatchLive = (strtoupper($status) === 'IN_PROGRESS');

        if (strtoupper($status) === 'COMPLETED' || $this->match->home_team_score > 0 || $this->match->away_team_score > 0) {
            $this->matchStats['home_score'] = $this->match->home_team_score;
            $this->matchStats['away_score'] = $this->match->away_team_score;
            $this->loadMatchEvents();
        }

        if (strtoupper($status) === 'IN_PROGRESS') {
            $matchState = $simulationService->getMatchState($this->match);
            $this->matchStats['current_minute'] = $matchState['current_minute'];
        }

        $this->canStartMatch =
            $this->isUserTeam &&
            $status === 'pending' &&
            now()->addMinutes(15)->gte($this->match->match_date) &&
            ($this->match->home_team_score == 0 && $this->match->away_team_score == 0);
    }

    public function startMatch()
    {
        if (!$this->canStartMatch) {
            return;
        }

        $this->isLoading = true;

        try {
            $simulationService = app(RealtimeMatchSimulationService::class);
            $result = $simulationService->startMatch($this->match);

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => $result['message']
            ]);

            $this->isMatchLive = true;
        } catch (Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to start match: ' . $e->getMessage()
            ]);
        }

        $this->isLoading = false;
    }

    public function handleViewersPresent($data)
    {
        $this->viewers = $data;
        $this->viewerCount = count($data);

        if ($this->viewerCount > 1) {
            $this->dispatch('notify', [
                'type' => 'info',
                'message' => "There are {$this->viewerCount} people watching this match"
            ]);
        }
    }

    public function handleNewViewer($data)
    {
        $this->viewers[$data['id']] = $data;
        $this->viewerCount = count($this->viewers);

        $user = $data['user'] ?? ['name' => 'Someone', 'id' => null];
        $userName = $user['name'] ?? 'Someone';

        if (isset($user['id']) && $user['id'] != Auth::id()) {
            $this->dispatch('notify', [
                'type' => 'info',
                'message' => "{$userName} joined the match"
            ]);
        }
    }

    public function handleViewerLeaving($data)
    {
        if (isset($this->viewers[$data['id']])) {
            $user = $this->viewers[$data['id']]['user'] ?? ['name' => 'Someone', 'id' => null];
            $userName = $user['name'] ?? 'Someone';

            unset($this->viewers[$data['id']]);
            $this->viewerCount = count($this->viewers);

            if (isset($user['id']) && $user['id'] != Auth::id()) {
                $this->dispatch('notify', [
                    'type' => 'info',
                    'message' => "{$userName} left the match"
                ]);
            }
        }
    }

    public function handleMatchUpdate($update)
    {
        $this->matchStats['current_minute'] = $update['current_minute'];
        $this->matchStats['home_score'] = $update['home_team']['score'];
        $this->matchStats['away_score'] = $update['away_team']['score'];
        $this->matchStats['home_possession'] = $update['home_team']['possession'];
        $this->matchStats['away_possession'] = $update['away_team']['possession'];
        $this->matchStats['home_shots'] = $update['home_team']['shots'];
        $this->matchStats['away_shots'] = $update['away_team']['shots'];
        $this->matchStats['home_shots_on_target'] = $update['home_team']['shots_on_target'];
        $this->matchStats['away_shots_on_target'] = $update['away_team']['shots_on_target'];

        if (isset($update['event']) && $update['event']) {
            $this->matchEvents[] = $update['event'];

            usort($this->matchEvents, function($a, $b) {
                return $a['minute'] <=> $b['minute'];
            });
        }

        if ($update['type'] === 'MATCH_END') {
            $this->isMatchLive = false;
        }
    }

    public function handleTacticChange($teamId, $newTactic)
    {
        if (!$this->isUserTeam || Auth::user()->currentTeam != $teamId) {
            $team = ($teamId == $this->homeTeam->getKey()) ? $this->homeTeam : $this->awayTeam;

            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => "{$team->name} has changed their tactic to " . str_replace('_', ' ', $newTactic)
            ]);
        }
    }

    public function reconnect()
    {
        try {
            $simulationService = app(RealtimeMatchSimulationService::class);
            $matchState = $simulationService->getMatchState($this->match);

            if (isset($matchState['current_minute'])) {
                $this->matchStats['current_minute'] = $matchState['current_minute'];
            }

            if (isset($matchState['home_team']['score'])) {
                $this->matchStats['home_score'] = $matchState['home_team']['score'];
            }

            if (isset($matchState['away_team']['score'])) {
                $this->matchStats['away_score'] = $matchState['away_team']['score'];
            }

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Reconnected to match"
            ]);
        } catch (Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => "Failed to reconnect: " . $e->getMessage()
            ]);
        }
    }

    public function render()
    {
        return view('livewire.match-viewer');
    }
}
