<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition()
    {
        return [
            'user_id' => null,
            'name' => $this->faker->unique()->company,
            'current_tactic' => 'DEFAULT_MODE',
            'team_rating' => 0,
            'team_budget' => 10000,
        ];
    }
}
