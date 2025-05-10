<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchModel extends Model
{
    use HasFactory;

    protected $table = 'game_matches';

    protected $fillable = [
        'home_team_id',
        'away_team_id',
        'home_team_score',
        'away_team_score',
        'match_date'
    ];

    protected $casts = [
        'match_date' => 'datetime',
    ];

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function teamLineup(): HasMany
    {
        return $this->hasMany(TeamLineup::class);
    }

    public function playerPerformances(): HasMany
    {
        return $this->hasMany(PlayerPerformance::class, 'match_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MatchEvent::class, 'match_id');
    }

    public function simulationStatus()
    {
        return $this->hasOne(MatchSimulationStatus::class, 'match_id')
            ->orderBy('created_at', 'desc');
    }
}
