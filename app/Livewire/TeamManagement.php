<?php

namespace App\Livewire;

use App\Http\Enums\PlayerPosition;
use App\Models\Formation;
use App\Models\LineupPlayer;
use App\Models\Player;
use App\Models\TeamLineup;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Team Management')]
class TeamManagement extends Component
{
    public $team;
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
    public $selectedPlayerForAssignment = null;
    public $teamTactic;

    protected $formationToPlayerPosition = [
        'GOALKEEPER' => 'GOALKEEPER',
        'CENTRE_BACK_LEFT' => 'CENTRE_BACK',
        'CENTRE_BACK_RIGHT' => 'CENTRE_BACK',
        'CENTRE_BACK_CENTER' => 'CENTRE_BACK',
        'FULLBACK_LEFT' => 'FULLBACK',
        'FULLBACK_RIGHT' => 'FULLBACK',
        'MIDFIELDER_LEFT' => 'MIDFIELDER',
        'MIDFIELDER_RIGHT' => 'MIDFIELDER',
        'MIDFIELDER_CENTER' => 'MIDFIELDER',
        'MIDFIELDER_CENTER_LEFT' => 'MIDFIELDER',
        'MIDFIELDER_CENTER_RIGHT' => 'MIDFIELDER',
        'DEFENSIVE_MIDFIELDER_LEFT' => 'MIDFIELDER',
        'DEFENSIVE_MIDFIELDER_RIGHT' => 'MIDFIELDER',
        'ATTACKING_MIDFIELDER_CENTER' => 'MIDFIELDER',
        'ATTACKING_MIDFIELDER_LEFT' => 'WINGER',
        'ATTACKING_MIDFIELDER_RIGHT' => 'WINGER',
        'WINGER_LEFT' => 'WINGER',
        'WINGER_RIGHT' => 'WINGER',
        'STRIKER' => 'STRIKER',
        'STRIKER_LEFT' => 'STRIKER',
        'STRIKER_RIGHT' => 'STRIKER',
        'STRIKER_CENTER' => 'STRIKER',
    ];

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
            redirect()->route('change-team');
        }

        $this->formations = Formation::all()->pluck('name', 'id');
        $this->players = Player::where('team_id', $this->team->getKey())->get();

        $this->lineup = TeamLineup::where('team_id', $this->team->getKey())
            ->whereNull('match_id')
            ->first();

        if (!$this->lineup) {
            $this->lineup = TeamLineup::create([
                'team_id' => $this->team->getKey(),
                'match_id' => null,
                'formation_id' => $this->formations->first()->getKey(),
                'tactic' => $this->team->current_tactic ?? 'DEFAULT_MODE',
            ]);

            foreach ($this->players as $player) {
                LineupPlayer::create([
                    'lineup_id' => $this->lineup->getKey(),
                    'player_id' => $player->getKey(),
                    'position' => $player->position,
                    'is_starter' => false,
                    'position_order' => 0,
                ]);
            }
        }

        $this->selectedFormationId = $this->lineup->formation_id;
        $this->selectedTactic = $this->lineup->tactic;
        $this->teamTactic = [
            'ATTACK_MODE' => 'Attack Mode',
            'DEFEND_MODE' => 'Defend Mode',
            'DEFAULT_MODE' => 'Default Mode'
        ];

        $this->loadPositionsForFormation();
        $this->loadExistingLineup();
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
        foreach ($this->positions as $position => $coords) {
            $this->lineupPlayers[$position] = null;
        }

        $dbPositionToFormation = [];
        foreach ($this->formationToPlayerPosition as $formPos => $dbPos) {
            if (!isset($dbPositionToFormation[$dbPos])) {
                $dbPositionToFormation[$dbPos] = [];
            }
            $dbPositionToFormation[$dbPos][] = $formPos;
        }

        $starterPlayers = LineupPlayer::where('lineup_id', $this->lineup->getKey())
            ->where('is_starter', true)
            ->with('player')
            ->get();

        $occupiedPositions = [];

        foreach ($starterPlayers as $lineupPlayer) {
            $player = $lineupPlayer->player;
            $dbPosition = $lineupPlayer->position;

            $potentialFormationPositions = $dbPositionToFormation[$dbPosition] ?? [];

            $bestPosition = null;
            foreach ($potentialFormationPositions as $formPos) {
                if (isset($this->positions[$formPos]) && !isset($occupiedPositions[$formPos])) {
                    if ($this->isPreferredPosition($player->position, $formPos)) {
                        $bestPosition = $formPos;
                        break;
                    } else if (!$bestPosition) {
                        $bestPosition = $formPos;
                    }
                }
            }

            if ($bestPosition) {
                $this->lineupPlayers[$bestPosition] = $player->getKey();
                $occupiedPositions[$bestPosition] = true;
            }
        }

        $allPlayerIds = $this->players->pluck('id')->toArray();
        $starterIds = array_filter($this->lineupPlayers);
        $this->benchPlayers = array_values(array_diff($allPlayerIds, $starterIds));
    }

    public function isPreferredPosition($playerType, $formationPosition): bool
    {
        return in_array($formationPosition, $this->positionPreferences[$playerType] ?? []);
    }

    public function changeFormation(): void
    {
        $this->loadPositionsForFormation();

        foreach($this->positions as $position => $coords) {
            $this->lineupPlayers[$position] = null;
        }

        $this->benchPlayers = $this->players->pluck('id')->toArray();

        $this->lineup->update(['formation_id' => $this->selectedFormationId]);

        LineupPlayer::where('lineup_id', $this->lineup->getKey())->delete();

        foreach ($this->players as $player) {
            LineupPlayer::create([
                'lineup_id' => $this->lineup->getKey(),
                'player_id' => $player->getKey(),
                'position' => $player->position,
                'is_starter' => false,
                'position_order' => 0,
            ]);
        }

        $this->dispatch('notify', [
            'type' => 'info',
            'message' => 'Formation changed to ' . Formation::find($this->selectedFormationId)->name
        ]);
    }

    public function changeTactic(): void
    {
        $this->lineup->update(['tactic' => $this->selectedTactic]);

        $this->dispatch('notify', [
            'type' => 'info',
            'message' => 'Tactic changed to ' . str_replace('_', ' ', $this->selectedTactic)
        ]);
    }

    public function selectPlayerForAssignment($playerId)
    {
        $this->selectedPlayerForAssignment = (int)$playerId;
    }

    public function cancelPlayerSelection()
    {
        $this->selectedPlayerForAssignment = null;
    }

    public function assignPlayerToPosition($position)
    {
        if ($this->selectedPlayerForAssignment) {
            $this->assignPlayer($this->selectedPlayerForAssignment, $position);
            $this->selectedPlayerForAssignment = null;
        }
    }

    public function assignPlayer($playerId, $position): void
    {
        $playerId = (int) $playerId;
        $position = (string) $position;

        foreach ($this->lineupPlayers as $pos => $pid) {
            if ($pid === $playerId) {
                $this->lineupPlayers[$pos] = null;
            }
        }

        $this->benchPlayers = array_values(array_diff($this->benchPlayers, [$playerId]));
        if (isset($this->lineupPlayers[$position]) && $this->lineupPlayers[$position]) {
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

        $this->saveLineup();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $player->name . ' assigned to ' . $this->formatPositionName($position)
        ]);
    }

    public function removePlayerFromLineup($position): void
    {
        if ($this->lineupPlayers[$position]) {
            $player = $this->players->firstWhere('id', $this->lineupPlayers[$position]);
            $this->benchPlayers[] = $this->lineupPlayers[$position];
            $this->lineupPlayers[$position] = null;

            $this->saveLineup();

            $this->dispatch('notify', [
                'type' => 'info',
                'message' => $player->name . ' removed from lineup'
            ]);
        }
    }

    public function saveLineup(): void
    {
        LineupPlayer::where('lineup_id', $this->lineup->getKey())->delete();

        foreach ($this->lineupPlayers as $formationPosition => $playerId) {
            if ($playerId) {
                $dbPosition = $this->formationToPlayerPosition[$formationPosition] ?? null;

                if ($dbPosition) {
                    LineupPlayer::create([
                        'lineup_id' => $this->lineup->getKey(),
                        'player_id' => $playerId,
                        'position' => $dbPosition,
                        'is_starter' => true,
                        'position_order' => 0,
                    ]);
                }
            }
        }

        foreach ($this->benchPlayers as $playerId) {
            $player = $this->players->firstWhere('id', $playerId);

            if ($player) {
                LineupPlayer::create([
                    'lineup_id' => $this->lineup->getKey(),
                    'player_id' => $playerId,
                    'position' => $player->position,
                    'is_starter' => false,
                    'position_order' => 0,
                ]);
            }
        }

        $this->team->update(['current_tactic' => $this->selectedTactic]);
    }

    public function formatPositionName($position): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $position)));
    }

    public function render()
    {
        return view('livewire.team-management');
    }
}
