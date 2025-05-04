<?php

namespace App\Livewire;

use App\Models\Team;
use Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Select Team')]
class TeamSelection extends Component
{
    public $teams;
    public $currentTeam;

    protected $listeners = [
        'deleteTeamConfirmed' => 'handleDeleteTeam',
        'refreshTeams' => '$refresh'
    ];

    public function mount()
    {
        $this->loadTeams();
        $this->currentTeam = Auth::user()->current_team_id;
    }

    protected function loadTeams()
    {
        $this->teams = Auth::user()
            ->teams()
            ->with([
                'players',
                'season.league',
                'season.standing'
            ])
            ->take(3)
            ->get();
    }

    public function changeCurrentTeam($id)
    {
        $newCurrentTeam = Auth::user()->teams()->find($id);

        if (!$newCurrentTeam) {
            return redirect()->back()->with('error', 'Team not found');
        }

        Auth::user()->update(['current_team_id' => $newCurrentTeam->id]);

        return redirect()->route('dashboard')
            ->with('status', 'Team switched successfully!');
    }

    public function handleDeleteTeam($teamId)
    {
        $user = Auth::user();
        $team = $user->teams()->find($teamId);

        if (!$team) {
            return;
        }

        // Handle current team change if needed
        if ($user->current_team_id === $team->id) {
            $newCurrentTeam = $user->teams()
                ->where('id', '!=', $team->id)
                ->first();

            $user->update(['current_team_id' => $newCurrentTeam?->id]);
        }

        $team->delete();

        // Refresh teams list
        $this->loadTeams();

        // Redirect if no teams left
        if ($user->teams()->count() === 0) {
            return redirect()->route('create-team')
                ->with('status', 'Last team deleted. Please create a new team.');
        }
    }

    public function render()
    {
        return view('livewire.team.team-selection');
    }
}
