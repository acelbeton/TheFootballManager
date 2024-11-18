<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Standing extends Model
{
    use HasFactory;

    protected $table = 'standing';

    protected $fillable = [
        'league_id',
        'team_id',
        'goals_scored',
        'goals_conceded',
        'points',
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
}
