<?php

namespace App\Livewire\Team;

use App\Helpers\StatsHelper;
use App\Http\Enums\PlayerPosition;
use App\Http\Enums\PrizeMoney;
use App\Models\League;
use App\Models\Player;
use App\Models\Season;
use App\Models\Standing;
use App\Models\Statistic;
use App\Models\Team;
use App\Services\LeagueManagerService;
use App\Services\LineupService;
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
    protected $lineupService;

    public function boot(LeagueManagerService $leagueManager, LineupService $lineupService)
    {
        $this->leagueManager = $leagueManager;
        $this->lineupService = $lineupService;
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
        $this->lineupService->createDefaultLineup($team);
        $this->leagueManager->setupLeague($league);
        $standing = Standing::where('team_id', $team->getKey())->first();

        if (!$standing) {
            Standing::create([
               'season_id' => $season->getKey(),
               'team_id' => $team->getKey(),
            ]);
        }

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

        $teamQualityTier = $this->determineTeamQualityTier($team);

        foreach ($positions as $position => $count) {
            for ($i = 0; $i < $count; $i++) {
                $playerTier = $this->getPlayerQualityTier($teamQualityTier);
                $enumPosition = PlayerPosition::from($position);
                $stats = StatsHelper::getStatsForPosition($enumPosition, $playerTier);
                $rating = StatsHelper::calculateOverallRating($stats, $enumPosition);
                $marketValue = StatsHelper::calculateMarketValue($rating, $enumPosition);

                $player = Player::create([
                    'name' => $this->generatePlayerName(),
                    'rating' => $rating,
                    'market_value' => $marketValue,
                    'team_id' => $team->getKey(),
                    'position' => $position,
                    'condition' => rand(80, 100),
                    'is_injured' => false,
                    'is_on_market' => rand(1, 10) > 8,
                ]);

                $player->statistics()->create($stats);
            }
        }
    }

    private function determineTeamQualityTier(Team $team): int
    {
        $baseQuality = $team->user_id ? 3 : rand(1, 5);
        if ($team->team_budget > 12000) $baseQuality++;
        if ($team->team_budget < 9000) $baseQuality--;

        return max(1, min(5, $baseQuality));
    }

    private function getPlayerQualityTier(int $teamTier): int
    {
        $variation = rand(-1, 1);
        $playerTier = $teamTier + $variation;

        if (rand(1, 100) <= 5) {
            $playerTier = 5;
        }

        if (rand(1, 100) <= 5) {
            $playerTier = 1;
        }

        return max(1, min(5, $playerTier));
    }

    private function generatePlayerName(): string
    {
        $roll = rand(1, 100);

        if ($roll <= 60) {
            $firstNames = ['Gábor', 'István', 'Péter', 'János', 'Zoltán', 'Ferenc', 'László', 'Attila', 'Tamás', 'Dániel'];
            $lastNames = ['Nagy', 'Kovács', 'Tóth', 'Szabó', 'Horváth', 'Kiss', 'Varga', 'Molnár', 'Németh', 'Farkas'];
        } else if ($roll <= 85) {
            $firstNames = ['James', 'David', 'Marco', 'Stefan', 'Hans', 'Pierre', 'Carlos', 'Francesco', 'Sergio', 'Anton'];
            $lastNames = ['Smith', 'Müller', 'Rodriguez', 'Ferrari', 'Martinez', 'Dubois', 'Schmidt', 'Rossi', 'Jensen', 'Brown'];
        } else {
            $firstNames = ['Mohamed', 'Ali', 'Juan', 'Hiroshi', 'Kim', 'Andrei', 'Oscar', 'Jamal', 'Wei', 'Mateo'];
            $lastNames = ['Silva', 'Santos', 'Tanaka', 'Park', 'Ivanov', 'Gonzalez', 'Lee', 'Nguyen', 'Zhang', 'Diallo'];
        }

        $firstName = $firstNames[array_rand($firstNames)];
        $lastName = $lastNames[array_rand($lastNames)];

        return "$firstName $lastName";
    }

    public function mount()
    {
        $this->leagues = League::all();
    }

    public function render()
    {
        if (Auth::user()->teams()->count() === 3) {
            return redirect()->route('dashboard');
        }

        return view('livewire.team.create-team');
    }
}
