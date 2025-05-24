<?php

namespace App\Services;

use App\Helpers\StatsHelper;
use App\Http\Enums\PlayerPosition;
use App\Models\Player;
use App\Models\Season;
use App\Models\Standing;
use App\Models\Team;
use Exception;
use Illuminate\Support\Facades\DB;
use Throwable;

class AITeamGeneratorService
{
    const MIN_TEAMS_PER_LEAGUE = 4;
    const TEAM_NAME_PREFIXES = ['FC', 'United', 'City', 'Athletic', 'Rovers', 'Wanderers', 'CF', 'Team'];
    const TEAM_NAME_LOCATIONS = [
        'London', 'Madrid', 'Paris', 'Milan', 'Munich', 'Manchester',
        'Amsterdam', 'Budapest', 'Szeged', 'Debrecen', 'Berlin',
        'Liverpool', 'Athens', 'Istanbul', 'Dublin', 'Barcelona',
    ];
    protected $lineupService;

    public function __construct(LineupService $lineupService)
    {
        $this->lineupService = $lineupService;
    }

    /**
     * @throws Throwable
     */
    public function generateTeamsForSeason(Season $season, int $targetTeamCount = self::MIN_TEAMS_PER_LEAGUE): array
    {
        $currentTeamCount = Team::where('season_id', $season->getKey())->count();
        $teamsToCreate = max(0, $targetTeamCount - $currentTeamCount);
        $createdTeams = [];

        if ($teamsToCreate > 0) {
            DB::transaction(function() use ($season, $teamsToCreate, &$createdTeams) {
                for ($i = 0; $i < $teamsToCreate; $i++) {
                    $team = $this->createAITeam($season);
                    $createdTeams[] = $team;
                }
            });
        }

        return $createdTeams;
    }

    /**
     * @throws Exception
     */
    private function createAITeam(Season $season): Team
    {
        $prefix = $this->getRandomElement(self::TEAM_NAME_PREFIXES);
        $location = $this->getRandomElement(self::TEAM_NAME_LOCATIONS);
        $teamName = $this->generateUniqueTeamName($prefix, $location);

        $baseTeamRating = rand(40, 80);

        $team = Team::create([
            'name' => $teamName,
            'user_id' => null,
            'season_id' => $season->getKey(),
            'team_rating' => $baseTeamRating,
            'team_budget' => rand(8000, 15000),
            'current_tactic' => $this->getRandomTactic()
        ]);

        Standing::create([
            'season_id' => $season->getKey(),
            'team_id' => $team->getKey(),
        ]);

        $this->assignPlayersToTeam($team, $baseTeamRating);
        $this->lineupService->createDefaultLineup($team);

        return $team;
    }

    private function generateUniqueTeamName(string $prefix, string $location): string
    {
        $baseName = "$location $prefix";
        $name = $baseName;
        $counter = 1;

        while (Team::where('name', $name)->exists()) {
            $name = "$baseName " . ($counter++);
        }

        return $name;
    }

    private function assignPlayersToTeam(Team $team, int $baseRating): void
    {
        $positions = [
            PlayerPosition::GOALKEEPER->value => 2,
            PlayerPosition::CENTRE_BACK->value => 4,
            PlayerPosition::FULLBACK->value => 4,
            PlayerPosition::MIDFIELDER->value => 6,
            PlayerPosition::WINGER->value => 4,
            PlayerPosition::STRIKER->value => 3,
        ];

        $teamQualityTier = intdiv($baseRating - 30, 10);
        $teamQualityTier = max(1, min(5, $teamQualityTier));

        foreach ($positions as $position => $count) {
            for ($i = 0; $i < $count; $i++) {
                $variation = rand(-1, 1);
                $playerTier = $teamQualityTier + $variation;
                if (rand(1, 100) <= 5) $playerTier = 5;
                if (rand(1, 100) <= 5) $playerTier = 1;

                $playerTier = max(1, min(5, $playerTier));

                $enumPosition = PlayerPosition::from($position);
                $stats = StatsHelper::getStatsForPosition($enumPosition, $playerTier);
                $rating = StatsHelper::calculateOverallRating($stats, $enumPosition);
                $marketValue = StatsHelper::calculateMarketValue($rating, $enumPosition);

                $player = Player::create([
                    'name' => $this->generatePlayerName(),
                    'rating' => $rating,
                    'team_id' => $team->id,
                    'position' => $position,
                    'market_value' => $marketValue,
                    'is_on_market' => rand(0, 10) > 8,
                    'condition' => rand(70, 100),
                    'is_injured' => false,
                ]);

                $player->statistics()->create($stats);
            }
        }

        $team->update([
            'team_rating' => (int) $team->players()->avg('rating')
        ]);
    }

    private function generatePlayerName(): string
    {
        $firstNames = [
            'John', 'James', 'David', 'Michael', 'Robert', 'Carlos', 'Juan', 'Francesco',
            'Marco', 'Stefan', 'Hans', 'Pierre', 'Gábor', 'István', 'Péter', 'László',
            'Mohamed', 'Ali', 'Ahmed', 'Hiroshi', 'Takashi', 'Wei', 'Li', 'Kim', 'Park'
        ];
        $lastNames = [
            'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Rodriguez', 'Rossi',
            'Ferrari', 'Müller', 'Schmidt', 'Dubois', 'Nagy', 'Kovács', 'Tóth', 'Horváth',
            'Silva', 'Santos', 'Tanaka', 'Suzuki', 'Zhang', 'Wang', 'Kim', 'Park', 'Nguyen'
        ];

        $firstName = $this->getRandomElement($firstNames);
        $lastName = $this->getRandomElement($lastNames);

        return "$firstName $lastName";
    }

    private function getRandomElement(array $array)
    {
        return $array[array_rand($array)];
    }

    private function getRandomTactic(): string
    {
        $tactics = ['ATTACK_MODE', 'DEFEND_MODE', 'DEFAULT_MODE'];
        return $this->getRandomElement($tactics);
    }
}
