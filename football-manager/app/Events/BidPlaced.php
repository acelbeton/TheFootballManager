<?php

namespace App\Events;

use App\Models\Market;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BidPlaced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $marketInput;

    /**
     * Create a new event instance.
     */
    public function __construct(Market $marketInput)
    {
        $this->marketInput = $marketInput;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('player-market'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'BidPlaced';
    }

    public function broadcastWith(): array
    {
        return [
          'market_input_id' => $this->marketInput->getKey(),
          'player_id' => $this->marketInput->player_id,
          'user_id' => $this->marketInput->user_id,
          'bid_amount' => $this->marketInput->current_bid_amount,
          'timestamp' => now()->toIso8601String(),
        ];
    }
}
