<?php

namespace Database\Seeders;

use App\Models\Formation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FormationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $formations = [
            [
                'name' => '4-4-2',
                'code' => '4-4-2',
                'positions' => [
                    'GOALKEEPER' => [50, 5],
                    'FULLBACK_LEFT' => [20, 20],
                    'CENTRE_BACK_LEFT' => [40, 20],
                    'CENTRE_BACK_RIGHT' => [60, 20],
                    'FULLBACK_RIGHT' => [80, 20],
                    'MIDFIELDER_LEFT' => [20, 50],
                    'MIDFIELDER_CENTER_LEFT' => [40, 50],
                    'MIDFIELDER_CENTER_RIGHT' => [60, 50],
                    'MIDFIELDER_RIGHT' => [80, 50],
                    'STRIKER_LEFT' => [40, 80],
                    'STRIKER_RIGHT' => [60, 80],
                ]
            ],
            [
                'name' => '4-3-3',
                'code' => '4-3-3',
                'positions' => [
                    'GOALKEEPER' => [50, 5],
                    'FULLBACK_LEFT' => [20, 20],
                    'CENTRE_BACK_LEFT' => [40, 20],
                    'CENTRE_BACK_RIGHT' => [60, 20],
                    'FULLBACK_RIGHT' => [80, 20],
                    'MIDFIELDER_LEFT' => [30, 50],
                    'MIDFIELDER_CENTER' => [50, 50],
                    'MIDFIELDER_RIGHT' => [70, 50],
                    'WINGER_LEFT' => [20, 80],
                    'STRIKER_CENTER' => [50, 80],
                    'WINGER_RIGHT' => [80, 80],
                ]
            ],
            [
                'name' => '3-5-2',
                'code' => '3-5-2',
                'positions' => [
                    'GOALKEEPER' => [50, 5],
                    'CENTRE_BACK_LEFT' => [30, 20],
                    'CENTRE_BACK_CENTER' => [50, 20],
                    'CENTRE_BACK_RIGHT' => [70, 20],
                    'MIDFIELDER_LEFT' => [15, 50],
                    'MIDFIELDER_CENTER_LEFT' => [35, 50],
                    'MIDFIELDER_CENTER' => [50, 50],
                    'MIDFIELDER_CENTER_RIGHT' => [65, 50],
                    'MIDFIELDER_RIGHT' => [85, 50],
                    'STRIKER_LEFT' => [40, 80],
                    'STRIKER_RIGHT' => [60, 80],
                ]
            ],
            [
                'name' => '4-2-3-1',
                'code' => '4-2-3-1',
                'positions' => [
                    'GOALKEEPER' => [50, 5],
                    'FULLBACK_LEFT' => [20, 20],
                    'CENTRE_BACK_LEFT' => [40, 20],
                    'CENTRE_BACK_RIGHT' => [60, 20],
                    'FULLBACK_RIGHT' => [80, 20],
                    'DEFENSIVE_MIDFIELDER_LEFT' => [40, 40],
                    'DEFENSIVE_MIDFIELDER_RIGHT' => [60, 40],
                    'ATTACKING_MIDFIELDER_LEFT' => [20, 60],
                    'ATTACKING_MIDFIELDER_CENTER' => [50, 60],
                    'ATTACKING_MIDFIELDER_RIGHT' => [80, 60],
                    'STRIKER' => [50, 80],
                ]
            ],
        ];

        foreach ($formations as $formation) {
            Formation::create($formation);
        }
    }
}
