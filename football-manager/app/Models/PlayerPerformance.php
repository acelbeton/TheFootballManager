<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerPerformance extends Model
{
    use HasFactory;

    protected $table = 'players_performances';

    protected $fillable = [
        'player_id',
        'match_id',
        'goals_scored',
        'assists',
        'rating',
        'minutes_played',
    ];

    public static function boot() {
        parent::boot();

        self::saving(function($model) {
           if ($model->rating < 1 || $model->rating > 100) {
               throw new Exception("Rating is out of range");
           }
        });
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id');
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(MatchModel::class, 'match_id');
    }
}
