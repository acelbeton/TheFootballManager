<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function updateRating(): void
    {
        $avgRating = (int) $this->players()->avg('rating') ?: 0;
        $this->update(['team_rating' => (int)round($avgRating)]);
    }

    public function currentTeamRelation(): HasOne
    {
        return $this->hasOne(User::class, 'current_team_id', 'id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class, 'season_id');
    }

    public function trainingSession(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }

    public function teamLineup(): HasMany
    {
        return $this->hasMany(TeamLineup::class);
    }
}
