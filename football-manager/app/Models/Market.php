<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Market extends Model
{
    use HasFactory;

    protected $table = 'market';
    protected $fillable = [
        'player_id',
        'current_bid_amount',
        'user_id',
    ];

    // TODO lehet nem ez kell
    public function player(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function user(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
