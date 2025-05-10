<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchEvent extends Model
{
    use HasFactory;

    protected $table = 'match_events';

    protected $fillable = [
        'match_id',
        'type',
        'minute',
        'team',
        'main_player_id',
        'main_player_name',
        'secondary_player_id',
        'secondary_player_name',
        'commentary',
        'home_score',
        'away_score'
    ];

    protected $casts = [
        'minute' => 'integer',
        'home_score' => 'integer',
        'away_score' => 'integer',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(MatchModel::class, 'match_id', 'id');
    }

    public function mainPlayer()
    {
        return $this->belongsTo(Player::class, 'main_player_id');
    }

    public function secondaryPlayer()
    {
        return $this->belongsTo(Player::class, 'secondary_player_id');
    }
}
