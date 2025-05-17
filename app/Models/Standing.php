<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Standing extends Model
{
    use HasFactory;

    protected $table = 'standings';

    protected $fillable = [
        'season_id',
        'team_id',
        'goals_scored',
        'goals_conceded',
        'points',
        'points_per_week',
        'matches_played',
        'matches_won',
        'matches_drawn',
        'matches_lost',
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function updateTeamPointsPerWeekAvg($teamId)
    {
        $sumPoints = TeamPerformance::where('team_id', $teamId)->sum('points');
        $weeksPlayed = TeamPerformance::where('team_id', $teamId)
            ->distinct('week_number')
            ->count('week_number');

        $avg = $weeksPlayed > 0 ? $sumPoints / $weeksPlayed : 0;

        $standing = Standing::where('team_id', $teamId)->first();
        if ($standing) {
            $standing->points_per_week_avg = $avg;
            $standing->save();
        }
    }

}
