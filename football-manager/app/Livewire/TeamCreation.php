<?php

namespace App\Livewire;

use App\Models\League;
use App\Models\Player;
use App\Models\Team;
use Livewire\Component;

class TeamCreation extends Component
{
    public function render()
    {
        return view('livewire.team-creation', [
            'leagues' => League::all(),
        ])->with('layouts.app');
    }
}
