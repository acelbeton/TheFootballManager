<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class League extends Model
{
    use HasFactory;

    protected $table = 'league';

    protected $fillable = [
        'name',
        'season',
        'prize_money_first',
        'prize_money_second',
        'prize_money_third',
        'prize_money_other',
    ];

    public function standing(): HasOne
    {
        return $this->hasOne(Standing::class);
    }
}
