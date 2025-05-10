<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LineupPlayer extends Model
{
    use HasFactory;

    protected $table = 'lineup_players';

    protected $fillable = [
        'lineup_id',
        'player_id',
        'position',
        'is_starter',
        'position_order',
    ];

    public function teamLineup()
    {
        return $this->belongsTo(TeamLineup::class);
    }

    public function player()
    {
        return $this->belongsTo(Player::class);
    }
}
