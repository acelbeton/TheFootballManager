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

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function updateTeamPointsPerWeekAvg($teamId)
    {
        // Gather sums/counts
        $sumPoints = TeamPerformance::where('team_id', $teamId)->sum('points');
        $weeksPlayed = TeamPerformance::where('team_id', $teamId)
            ->distinct('week_number')
            ->count('week_number');

        // Compute average
        $avg = $weeksPlayed > 0 ? $sumPoints / $weeksPlayed : 0;

        // Now find the relevant standings row (for the correct season)
        // You might pass the season_id or find it in another way.
        $standing = Standing::where('team_id', $teamId)->first();
        if ($standing) {
            $standing->points_per_week_avg = $avg;
            $standing->save();
        }
    }

}
