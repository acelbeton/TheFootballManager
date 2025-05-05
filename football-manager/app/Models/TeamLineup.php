<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamLineup extends Model
{
    use HasFactory;

    protected $table = 'team_lineup';

    protected $fillable = [
        'team_id',
        'match_id',
        'formation_id',
        'tactic'
    ];

    public function team()
    {
        $this->belongsTo(Team::class);
    }

    public function match()
    {
        $this->belongsTo(MatchModel::class);
    }

    public function formation()
    {
        $this->belongsTo(Formation::class);
    }

    public function lineupPlayer()
    {
        $this->hasMany(LineupPlayer::class);
    }
}
