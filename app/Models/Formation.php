<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Formation extends Model
{
    use HasFactory;

    protected $table = 'formations';

    protected $fillable = [
        'name',
        'code',
        'positions',
    ];

    protected $casts = [
        'positions' => 'array',
    ];

    public function matchLineups()
    {
        return $this->hasMany(TeamLineup::class);
    }
}
