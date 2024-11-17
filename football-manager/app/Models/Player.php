<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Player extends Model
{
    use HasFactory;

    protected $table = 'player';

    protected $fillable = [
        'name',
        'team_id',
        'position',
        'market_value',
        'condition',
        'is_injured',
    ];

    protected $appends = ['rating'];

    public function getRatingAttribute(): int
    {
        return $this->statistics()
            ->selectRaw('AVG(attacking + defending + stamina + technical_skills + speed + tactical_sense) / 6 AS avg_rating')
            ->value('avg_rating');
    }

    public function statistics(): HasOne
    {
        return $this->hasOne(Statistic::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
