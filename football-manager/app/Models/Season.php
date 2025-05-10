<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Season extends Model
{
    use HasFactory;

    protected $table = 'seasons';

    protected $fillable = [
        'league_id',
        'start_date',
        'end_date',
        'open',
        'prize_money_first',
        'prize_money_second',
        'prize_money_third',
        'prize_money_other',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class, 'season_id');
    }

    public function standing(): HasOne
    {
        return $this->hasOne(Standing::class);
    }

    public function matches()
    {
        return MatchModel::whereIn('home_team_id', $this->teams->pluck('id'))
            ->orWhereIn('away_team_id', $this->teams->pluck('id'));
    }
}
