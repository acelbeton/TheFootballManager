<?php

namespace App\Livewire;

use App\Models\MatchModel;
use App\Models\Player;
use App\Models\Season;
use App\Models\Standing;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    public $team;
    public $upcomingMatches;
    public $leagueStandings;
    public $rosterSummary;
    public $marketSummary;
    public $leagueInfo;
    public $teamPerformance;

    public function mount() {
        $this->team = Auth::user()->currentTeam;

        $this->upcomingMatches = MatchModel::where(function($query) {
            $query->where('home_team_id', $this->team->getKey())
                ->orWhere('away_team_id', $this->team->getKey());
        })
        ->where('match_date', '>', now())
        ->orderBy('match_date')
        ->get();

        $season = Season::where('id', $this->team->season_id)->first();
        if ($season) {
            $this->leagueStandings = Standing::where('season_id', $season->getKey())
                ->with('team')
                ->orderBy('points_per_week_avg', 'desc')
                ->orderBy('goals_scored', 'desc')
                ->get();

            $this->leagueInfo = [
                'name' => $season->league->name,
                'start_date' => $season->start_date,
                'end_date' => $season->end_date,
                'is_open' => $season->open,
                'prize_money' => [
                    'first' => $season->prize_money_first,
                    'second' => $season->prize_money_second,
                    'third' => $season->prize_money_third,
                ]
            ];
        }

        $players = Player::where('team_id', $this->team->getKey())->get();
        $this->rosterSummary = [
            'total' => $players->count(),
            'avg_rating' => $players->avg('rating') ?? 0,
            'by_position' => [
                'GOALKEEPER' => $players->where('position', 'GOALKEEPER')->count(),
                'CENTRE_BACK' => $players->where('position', 'CENTRE_BACK')->count(),
                'FULLBACK' => $players->where('position', 'FULLBACK')->count(),
                'MIDFIELDER' => $players->where('position', 'MIDFIELDER')->count(),
                'WINGER' => $players->where('position', 'WINGER')->count(),
                'STRIKER' => $players->where('position', 'STRIKER')->count(),
            ],
            'injured' => $players->where('is_injured', true)->count()
        ];

        $this->marketSummary = [
            'total_on_market' => Player::where('is_on_market', true)->count(),
            'team_budget' => $this->team->team_budget
        ];

        $teamStanding = $this->getTeamStanding();
        $this->teamPerformance = [
            'matches_played' => $teamStanding?->matches_played ?? 0,
            'matches_won' => $teamStanding?->matches_won ?? 0,
            'matches_drawn' => $teamStanding?->matches_drawn ?? 0,
            'matches_lost' => $teamStanding?->matches_lost ?? 0,
            'goals_scored' => $teamStanding?->goals_scored ?? 0,
            'goals_conceded' => $teamStanding?->goals_conceded ?? 0,
            'points' => $teamStanding?->points ?? 0,
            'position' => $this->getTeamPosition(),
        ];
    }

    private function getTeamStanding()
    {
        if (!$this->team) return null;

        return Standing::where('team_id', $this->team->getKey())
            ->where('season_id', $this->team->season_id)
            ->first();
    }

    private function getTeamPosition()
    {
        if (!$this->team) return null;

        $standings = Standing::where('season_id', $this->team->season_id)
            ->orderBy('points', 'desc')
            ->orderBy('goals_scored', 'desc')
            ->get();

        foreach ($standings as $index => $standing) {
            if ($standing->team_id === $this->team->getKey()) {
                return $index + 1;
            }
        }

        return null;
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}
