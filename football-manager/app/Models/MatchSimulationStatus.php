<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchSimulationStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'status',
        'job_id',
        'current_minute'
    ];

    public function match()
    {
        return $this->belongsTo(MatchModel::class, 'match_id');
    }
}
