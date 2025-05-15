<?php

namespace Database\Seeders;

use App\Models\League;
use DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LeagueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminId = 1;

        $leagues = [
            'English League',
            'Spanish League',
            'German League',
            'Italian League',
            'French League',
            'Dutch League',
            'Portuguese League',
            'Hungarian League',
            'Turkish League',
            'Japanese League',
            'Korean League',
            'Chinese League'
        ];

        foreach ($leagues as $leagueName) {
            League::create([
                'name' => $leagueName,
                'created_by' => $adminId,
            ]);
        }
    }
}
