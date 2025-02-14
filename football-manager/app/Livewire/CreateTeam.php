<?php

namespace App\Livewire;

use App\Models\Player;
use App\Models\Team;
use Livewire\Attributes\Rule;
use Livewire\Component;

// a teamcreation a regisztráció után jön
// ezen kívűl a team management komponens része lesz
// TODO liga kérdés -> lehet-e egy csapat több ligában, ha igen, hogyan + mikor lehet ligákba lépni?
class CreateTeam extends Component
{

    #[Rule('required|string|max:255|unique:team,name')]
    public $name;
    #[Rule('required|exists:leagues,id')]
    public $league;

    public function createTeam()
    {
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
}
