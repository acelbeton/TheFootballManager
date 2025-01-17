<?php

namespace App\Livewire;

use App\Models\Team;
use Livewire\Component;

class Teams extends Component
{
    public $teams;
    public $name;
    public $budget;
    public $current_tactic;

    public function mount()
    {
        $this->teams = Team::all();
    }

    public function addTeam()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'budget' => 'required|numeric|min:0',
            'current_tactic' => 'required|string|max:255',
        ]);

        Team::create([
            'name' => $this->name,
            'budget' => $this->budget,
            'current_tactic' => $this->current_tactic,
        ]);

        $this->reset(['name', 'budget', 'current_tactic']);
        $this->teams = Team::all();
        session()->flash('message', 'Team added successfully.');
    }

    public function deleteTeam($id)
    {
        Team::findOrFail($id)->delete();
        $this->teams = Team::all(); // Refresh the list
        session()->flash('message', 'Team deleted successfully.');
    }

    public function render()
    {
        return view('livewire.teams')
            ->layout('components.layouts.app', ['title' => 'Teams Management']);
    }
}
