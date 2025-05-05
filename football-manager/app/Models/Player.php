<?php

//declare(strict_types=1);

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Player extends Model
{
    use HasFactory;

    protected $table = 'players';

    protected $fillable = [
        'name',
        'team_id',
        'position',
        'market_value',
        'condition',
        'is_injured',
    ];

    protected $appends = ['rating'];

    public static function boot()
    {
        parent::boot();

        self::saving(function (Player $player) {
            if ($player->statistics && ($player->rating < 1 || $player->rating > 100)) {
                throw new Exception('Rating must be between 1 and 100.');
            }
            if ($player->condition < 1 || $player->condition > 100) {
                throw new Exception('Condition must be between 1 and 100.');
            }
        });
    }

    public function getRatingAttribute(): int
    {
        $stat = $this->statistics;
        if (!$stat) {
            return 0;
        }
        return (int) round(
            ($stat->attacking + $stat->defending + $stat->stamina + $stat->technical_skills + $stat->speed + $stat->tactical_sense) / 6
        );
    }

    public function statistics(): HasOne
    {
        return $this->hasOne(Statistic::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function lineupPlayer(): HasMany
    {
        return $this->hasMany(LineupPlayer::class);
    }
}
