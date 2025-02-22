<?php

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
    ];

    protected $casts = ['team_budget' => 'float'];
    protected $appends = ['team_quality'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function player(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class, 'season_id');
    }

    public function getTeamQualityAttribute(): int
    {
        return $this->player()
            ->selectRaw('AVG(rating) as team_rating')
            ->value('team_quality');
    }
}
