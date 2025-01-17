<?php

namespace App\Services;

use App\Models\Market;
use App\Models\Player;
use App\Models\Team;

class TransactionService
{
    /**
     * Place a bid for a player in the market.
     */
    public function placeBid(int $playerId, int $userId, int $bidAmount)
    {
        $marketEntry = Market::firstOrCreate(['player_id' => $playerId]);

        if ($bidAmount <= $marketEntry->current_bid_amount) {
            throw new \Exception('Bid amount must be higher than the current bid.');
        }

        $marketEntry->update([
            'current_bid_amount' => $bidAmount,
            'user_id' => $userId,
        ]);

        return $marketEntry;
    }

    /**
     * Finalize a player's transfer.
     */
    public function finalizeTransfer(int $marketId, int $teamId)
    {
        $marketEntry = Market::findOrFail($marketId);
        $player = Player::findOrFail($marketEntry->player_id);
        $team = Team::findOrFail($teamId);

        $player->update(['team_id' => $teamId]);
        $marketEntry->delete();

        $team->update(['budget' => $team->budget - $marketEntry->current_bid_amount]);

        return $player;
    }
}

