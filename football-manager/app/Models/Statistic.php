<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Statistic extends Model
{
    use HasFactory;

    protected $table = 'statistics';

    protected $fillable = [
        'player_id',
        'attacking',
        'defending',
        'stamina',
        'technical_skills',
        'speed',
        'tactical_sense'
    ];

    public static function boot() {
        parent::boot();

        static::saving(function($statistic) {
           foreach (['attacking', 'defending', 'stamina', 'technical_skills', 'speed', 'tactical_sense'] as $field) {
               if ($statistic->field < 1 || $statistic->field > 100) {
                   throw new Exception("Statistic $field must be between 1 and 100");
               }
           }
        });
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
