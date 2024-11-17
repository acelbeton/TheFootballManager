<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $table = 'team';
    protected $fillable = [
        'name',
        'user_id',
        'current_tactic',
        'budget',
    ];

    protected $appends = ['team_quality'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function player(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function getTeamQualityAttribute(): int
    {
        return $this->player()
            ->selectRaw('AVG(rating) as team_quality')
            ->value('team_quality');
    }
}
