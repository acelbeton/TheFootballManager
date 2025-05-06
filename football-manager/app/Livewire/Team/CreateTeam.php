<?php

namespace App\Livewire\Team;

use App\Helpers\StatsHelper;
use App\Http\Enums\PlayerPosition;
use App\Http\Enums\PrizeMoney;
use App\Models\League;
use App\Models\Player;
use App\Models\Season;
use App\Models\Statistic;
use App\Models\Team;
use App\Services\LeagueManagerService;
use Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;

class CreateTeam extends Component
{
    #[Validate('required|string|max:255|unique:teams,name')]
    public $name;

    public $leagues;

    #[Validate('required|exists:leagues,id')]
    public $selectedLeagueId;

    protected $leagueManager;

    public function boot(LeagueManagerService $leagueManager)
    {
        $this->leagueManager = $leagueManager;
    }

    public function createTeam()
    {
        $this->validate();

        $league = $this->leagues->find($this->selectedLeagueId);

        $season = Season::where('league_id', $league->getKey())
            ->where('open', true)
            ->first();

        if (!$season) {
            $season = Season::create([
                'league_id' => $league->getKey(),
                'start_date' => now(),
                'open' => true,
                'end_date' => now()->addWeeks(8),
                'prize_money_first' => PrizeMoney::PRIZE_MONEY_FIRST,
                'prize_money_second' => PrizeMoney::PRIZE_MONEY_SECOND,
                'prize_money_third' => PrizeMoney::PRIZE_MONEY_THIRD,
                'prize_money_other' => PrizeMoney::PRIZE_MONEY_OTHER,
            ]);
        }

        $team = Team::create([
            'name' => $this->name,
            'user_id' => auth()->id(),
            'season_id' => $season->getKey(),
        ]);

        Auth::user()->update(['current_team_id' => $team->getKey()]);

        $this->assignRandomPlayers($team);

        $this->leagueManager->setupLeague($league);

        $this->redirect(route('dashboard'), navigate: true);
    }

    private function assignRandomPlayers(Team $team)
    {
        $positions = [
            PlayerPosition::GOALKEEPER->value => 2,
            PlayerPosition::CENTRE_BACK->value => 3,
            PlayerPosition::FULLBACK->value => 2,
            PlayerPosition::MIDFIELDER->value => 4,
            PlayerPosition::WINGER->value => 2,
            PlayerPosition::STRIKER->value => 2,
        ];

        foreach ($positions as $position => $count) {
            Player::factory($count)->create([
                'team_id'  => $team->id,
                'position' => $position,
            ])->each(function ($player) use ($position) {
                $enumPosition = PlayerPosition::from($position);
                $stats = StatsHelper::getStatsForPosition($enumPosition);
                $player->statistics()->create($stats);
            });
        }

        $this->updateTeamRating($team);
    }

    private function updateTeamRating(Team $team)
    {
        $team->update([
            'team_rating' => (int) $team->players()->avg('rating')
        ]);
    }

    public function mount()
    {
        $this->leagues = League::all();
    }

    public function render()
    {
        if (Auth::user()->teams()->count() > 3) {
            return redirect()->route('dashboard');
        }

        return view('livewire.team.create-team');
    }
}
