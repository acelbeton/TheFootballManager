<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Statistic extends Model
{
    use HasFactory;

    protected $table = 'statistic';

    protected $fillable = [
        'player_id',
        'attacking',
        'defending',
        'stamina',
        'technical_skills',
        'speed',
        'tactical_sense'
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
