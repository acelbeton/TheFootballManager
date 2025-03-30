<?php

namespace App\Livewire;

use App\Models\Team;
use Auth;
use Livewire\Component;

class TeamSelection extends Component
{
    public $teams;

    public $currentTeam;

    public function changeCurrentTeam()
    {
        // változtatjuk a current_team_id-t
    }

    public function mount()
    {
        $this->teams = Auth::user()
            ->teams()
            ->with([
                'season.league',
                'season.standing'
            ])->take(3)
            ->get();

        $this->currentTeam = Auth::user()->current_team_id;
    }

    public function render()
    {
        return view('livewire.team.team-selection');
    }
}
