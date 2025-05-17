<?php

namespace Database\Factories;

use App\Models\League;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\League>
 */
class LeagueFactory extends Factory
{
    protected $model = League::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'created_by' => 1,
        ];
    }
}
