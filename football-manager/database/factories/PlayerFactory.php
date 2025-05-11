<?php

namespace Database\Factories;

use App\Helpers\StatsHelper;
use App\Http\Enums\PlayerPosition;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Player>
 */
class PlayerFactory extends Factory
{
    protected $model = Player::class;

    public function definition()
    {
        $positions = [
            PlayerPosition::GOALKEEPER->value,
            PlayerPosition::CENTRE_BACK->value,
            PlayerPosition::FULLBACK->value,
            PlayerPosition::MIDFIELDER->value,
            PlayerPosition::WINGER->value,
            PlayerPosition::STRIKER->value
        ];

        $position = $this->faker->randomElement($positions);
        $enumPosition = PlayerPosition::from($position);

        $name = $this->generatePlayerName();

        return [
            'name' => $name,
            'rating' => 50,
            'market_value' => 50000,
            'condition' => 100,
            'is_injured' => false,
            'position' => $position,
            'team_id' => null,
            'is_on_market' => rand(1, 10) > 8,
        ];
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

    public function configure()
    {
        return $this->afterCreating(function (Player $player) {
            $position = PlayerPosition::from($player->position);
            $stats = StatsHelper::getStatsForPosition($position);
            $player->statistics()->create($stats);
            $rating = StatsHelper::calculateOverallRating($stats, $position);
            $marketValue = StatsHelper::calculateMarketValue($rating, $position);

            $player->update([
                'rating' => $rating,
                'market_value' => $marketValue
            ]);
        });
    }
}
