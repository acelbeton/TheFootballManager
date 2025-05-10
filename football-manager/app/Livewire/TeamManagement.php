<?php

namespace App\Livewire;

use App\Http\Enums\PlayerPosition;
use App\Models\Formation;
use App\Models\LineupPlayer;
use App\Models\MatchModel;
use App\Models\Player;
use App\Models\TeamLineup;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Team Management')]
class TeamManagement extends Component
{
    public $team;
    public $upcomingMatch;
    public $formations;
    public $selectedFormationId;
    public $selectedTactic;
    public $players;
    public $lineup;
    public $positions;
    public $positionMappings;
    public $lineupPlayers = [];
    public $benchPlayers = [];
    public $showWarning = false;
    public $warningMessage = '';

    protected $positionPreferences = [
        'GOALKEEPER' => ['GOALKEEPER'],
        'CENTRE_BACK' => ['CENTRE_BACK_LEFT', 'CENTRE_BACK_RIGHT', 'CENTRE_BACK_CENTER'],
        'FULLBACK' => ['FULLBACK_LEFT', 'FULLBACK_RIGHT'],
        'MIDFIELDER' => ['MIDFIELDER_LEFT', 'MIDFIELDER_RIGHT', 'MIDFIELDER_CENTER', 'MIDFIELDER_CENTER_LEFT', 'MIDFIELDER_CENTER_RIGHT', 'DEFENSIVE_MIDFIELDER_LEFT', 'DEFENSIVE_MIDFIELDER_RIGHT', 'ATTACKING_MIDFIELDER_CENTER'],
        'WINGER' => ['WINGER_LEFT', 'WINGER_RIGHT', 'ATTACKING_MIDFIELDER_LEFT', 'ATTACKING_MIDFIELDER_RIGHT'],
        'STRIKER' => ['STRIKER', 'STRIKER_LEFT', 'STRIKER_RIGHT', 'STRIKER_CENTER'],
    ];

    public function mount(): void
    {
        $this->team = Auth::user()->currentTeam;

        if (!$this->team) {
            redirect()->route('change-team'); // TODO maybe smth else here
        }

        $this->upcomingMatch = MatchModel::where(function($query) {
            $query->where('home_team_id', $this->team->getKey())
                ->orWhere('away_team_id', $this->team->getKey());
        })
        ->where('match_date', '>', now())
        ->orderBy('match_date')
        ->first();

        $this->formations = Formation::all();

        $this->players = Player::where('team_id', $this->team->getKey())->get();

        if ($this->upcomingMatch) {
            $this->lineup = TeamLineup::firstOrCreate(
                ['team_id' => $this->team->getKey(), 'match_id' => $this->upcomingMatch->getKey()],
                ['formation_id' => $this->formations->first()->getKey(), $this->team->current_tactic]
            );

            $this->selectedFormationId = $this->lineup->formation_id;
            $this->selectedTactic = $this->lineup->tactic;

            $this->loadPositionsForFormation();

            $this->loadExistingLineup();
        } else {
            $this->selectedFormationId = $this->formations->first()->getKey();
            $this->selectedTactic = $this->team->current_tactic;
            $this->loadPositionsForFormation();

            foreach ($this->positions as $position => $cords) {
                $this->lineupPlayers[$position] = null;
            }

            $this->benchPlayers = $this->players->pluck('id')->toArray();
        }
    }

    public function loadPositionsForFormation(): void
    {
        $formation = Formation::find($this->selectedFormationId);
        $this->positions = $formation->positions;

        $this->positionMappings = [];
        foreach (PlayerPosition::cases() as $position) {
            $this->positionMappings[$position->value] = [];

            foreach ($this->positions as $formationPosition => $coords) {
                if ($this->isPreferredPosition($position->value, $formationPosition)) {
                    $this->positionMappings[$position->value][] = $formationPosition;
                }
            }
        }
    }

    public function loadExistingLineup(): void
    {
        foreach($this->positions as $position => $cords) {
            $this->lineupPlayers[$position] = null;
        }

        $playerPositions = LineupPlayer::where('lineup_id', $this->lineup->getKey())
            ->where('is_starter', true)
            ->get();

        foreach ($playerPositions as $playerPosition) {
            $this->lineupPlayers[$playerPosition->position] = $playerPosition->player_id;
        }

        $startingPlayerIds = $playerPositions->pluck('player_id')->toArray();
        $this->benchPlayers = $this->players->whereNotIn('id', $startingPlayerIds)->pluck('id')->toArray();
    }

    public function isPreferredPosition($playerType, $formationPosition): bool
    {
        return in_array($formationPosition, $this->positionPreferences[$playerType] ?? []);
    }

    public function changeFormation(): void
    {
        $this->loadPositionsForFormation();

        foreach($this->positions as $position => $cords) {
            $this->lineupPlayers[$position] = null;
        }

        $this->benchPlayers = $this->players->pluck('id')->toArray();

        if ($this->upcomingMatch) {
            $this->lineup->update(['formation_id' => $this->selectedFormationId]);

            LineupPlayer::where('lineup_id', $this->lineup->getKey())->delete();
        }
    }

    public function changeTactic(): void
    {
        if ($this->upcomingMatch) {
            $this->lineup->update(['tactic' => $this->selectedTactic]);
        }
    }

    public function assignPlayer($playerId, $position): void
    {
        foreach ($this->lineupPlayers as $pos => $pid) {
            if ($pid === $playerId) {
                $this->lineupPlayers[$pos] = null;
            }
        }

        $this->benchPlayers = array_diff($this->benchPlayers, [$playerId]);

        if ($this->lineupPlayers[$position]) {
            $this->benchPlayers[] = $this->lineupPlayers[$position];
        }

        $this->lineupPlayers[$position] = $playerId;

        $player = $this->players->firstWhere('id', $playerId);
        if (!$this->isPreferredPosition($player->position, $position)) {
            $this->showWarning = true;
            $this->warningMessage = "Warning: " . $player->name . " is not naturally a " . $this->formatPositionName($position) . ". This may affect their performance.";
        } else {
            $this->showWarning = false;
        }

        if ($this->upcomingMatch) {
            $this->saveLineup();
        }
    }

    public function removePlayerFromLineup($position): void
    {
        if ($this->lineupPlayers[$position]) {
            $this->benchPlayers[] = $this->lineupPlayers[$position];

            $this->lineupPlayers[$position] = null;

            if ($this->upcomingMatch) {
                $this->saveLineup();
            }
        }
    }

    public function saveLineup(): void
    {
        LineupPlayer::where('lineup_id', $this->lineup->getKey())->delete();

        foreach ($this->lineupPlayers as $pos => $playerId) {
            if ($playerId) {
                LineupPlayer::create([
                   'lineup_id' => $this->lineup->getKey(),
                   'player_id' => $playerId,
                   'position' => $pos,
                   'is_starter' => true,
                ]);
            }
        }

        foreach ($this->benchPlayers as $playerId) {
            LineupPlayer::create([
               'lineup_id' => $this->lineup->getKey(),
               'player_id' => $playerId,
               'position' => 'BENCH',
               'is_starter' => true,
            ]);
        }

        $this->team->update(['current_tactic' => $this->selectedTactic]);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Lineup saved'
        ]);
    }

    public function formatPositionName($position): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $position)));
    }

    public function render(): \Illuminate\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
    {
        return view('livewire.team-management');
    }
}
