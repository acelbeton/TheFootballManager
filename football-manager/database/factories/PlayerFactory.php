<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Player>
 */
class PlayerFactory extends Factory
{
    protected $model = Player::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'rating' => random_int(50, 100),
            'market_value' => random_int(50000, 500000),
            'condition' => 100,
            'is_injured' => false,
            'position' => 'Midfielder',
            'team_id' => null,
        ];
    }
}
