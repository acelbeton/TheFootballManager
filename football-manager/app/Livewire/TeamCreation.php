<?php

namespace App\Livewire;

use App\Models\League;
use App\Models\Player;
use App\Models\Team;
use Livewire\Component;

class TeamCreation extends Component
{
    public $name;
    public $league;

    public function createTeam()
    {
        $this->validate([
            'name' => 'required|string|max:255|unique:team,name',
            'league' => 'required|exists:leagues,id',
        ]);

        $team = Team::create([
            'user_id' => auth()->id(),
            'name' => $this->name,
        ]);

        $this->assignRandomPlayers($team);

        return redirect()->route('dashboard')->with('success', 'Team created successfully with 18 players.');
    }

    private function assignRandomPlayers(Team $team)
    {
        $positions = [
            'goalkeeper' => 2,
            'centre-back' => 4,
            'fullback' => 2,
            'midfielder' => 6,
            'winger' => 2,
            'striker' => 2,
        ];

        foreach ($positions as $position => $count) {
            Player::factory($count)->create([
                'team_id' => $team->id,
                'position' => $position,
            ]);
        }

        $this->updateTeamRating($team);
    }

    private function updateTeamRating(Team $team)
    {
        $team->update([
            'team_rating' => round(
                $team->players()->avg('rating')
            ),
        ]);
    }

    public function render()
    {
        return view('livewire.team-creation', [
            'leagues' => League::all(),
        ]);
    }
}
