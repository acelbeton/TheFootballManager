<?php

namespace App\Livewire\Team;

use App\Http\Enums\PrizeMoney;
use App\Models\League;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use Livewire\Attributes\Validate;
use Livewire\Component;

// a teamcreation a regisztráció után jön
// ezen kívűl a team management komponens része lesz
// egy csapat egy ligában van, de egy játékosnak lehet maximum 3 csapata
class CreateTeam extends Component
{

    #[Validate('required|string|max:255|unique:teams,name')]
    public $name;
//    #[Rule('required|exists:leagues,id')]
//    public $league;
    public $leagues;

    #[Validate('required|exists:leagues,id')]
    public $selectedLeagueId;
    public function createTeam()
    {
        $this->validate();


        $league = $this->leagues->find($this->selectedLeagueId);

        $season = Season::where('league_id', $league->id)
            ->firstOrCreate([
                'league_id' => $league->id,
                'start_date' => now(),
                'open' => true,
                'end_date' => now()->addWeeks(4),
                'prize_money_first' => PrizeMoney::PRIZE_MONEY_FIRST,
                'prize_money_second' => PrizeMoney::PRIZE_MONEY_SECOND,
                'prize_money_third' => PrizeMoney::PRIZE_MONEY_THIRD,
                'prize_money_other' => PrizeMoney::PRIZE_MONEY_OTHER,

            ]);

        $team = Team::create([
            'name' => $this->name,
            'user_id' => auth()->id(),
            'season_id' => $season->getKey(),
        ]);

        $this->assignRandomPlayers($team);

        $this->redirect(route('dashboard'), navigate: true);
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

    public function mount()
    {
        $this->leagues = League::all();
    }
    public function render()
    {
        return view('livewire.team.create-team');
    }
}
