<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $table = 'teams';
    protected $fillable = [
        'name',
        'user_id',
        'season_id',
        'current_tactic',
        'season_id',
        'team_rating',
    ];

    protected $casts = ['team_budget' => 'float'];
//    protected $appends = ['team_rating'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class, 'season_id');
    }


    // Ez lehet, hogy nem kell, mivel most fillable a team_rating
    public function getTeamRatingAttribute(): int
    {
        $players = $this->players;
        if ($players->isEmpty()) {
            return 0;
        }

        return (int) $players->avg($players->rating);
    }
}
