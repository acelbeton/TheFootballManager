<?php

namespace App\Livewire;

use App\Models\League;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TeamCreation extends Component
{
    public $name;
    public $league_id;
    public $leagues;

    public function mount()
    {
        // Load leagues for the dropdown
        $this->leagues = League::all();
    }

    public function createTeam()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'league_id' => 'required|exists:leagues,id',
        ]);

        Team::create([
            'name' => $this->name,
            'user_id' => Auth::id(), // Associate the team with the logged-in user
            'league_id' => $this->league_id,
            'current_tactic' => '4-4-2', // Default tactic
            'budget' => 1000000, // Initial budget
        ]);

        // Reset form fields
        $this->reset(['name', 'league_id']);

        // Flash a success message
        session()->flash('message', 'Team created successfully!');
    }

    public function render()
    {
        return view('livewire.team-creation');
    }
}

