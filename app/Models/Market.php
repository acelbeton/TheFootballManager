<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Market extends Model
{
    use HasFactory;

    protected $table = 'market_inputs';

    protected $fillable = [
        'player_id',
        'current_bid_amount',
        'user_id',
        'bidding_end_date'
    ];

    protected $casts = [
        'bidding_end_date' => 'datetime',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
