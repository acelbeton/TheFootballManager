<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerPerformance extends Model
{
    use HasFactory;

    protected $table = 'players_performance';

    protected $fillable = [
        'player_id',
        'match_id',
        'goals_scored',
        'assists',
        'rating',
        'minutes_played',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id');
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(MatchModel::class, 'match_id');
    }
}
