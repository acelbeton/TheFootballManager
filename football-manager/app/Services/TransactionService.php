<?php

namespace App\Services;

use App\Events\BidPlaced;
use App\Models\Market;
use App\Models\Player;
use App\Models\Team;
use DB;
use Exception;
use Illuminate\Support\Carbon;

class TransactionService
{
    /**
     * @throws Exception
     */
    public function placeBid(int $playerId, int $userId, int $bidAmount)
    {
        $player = Player::findOrFail($playerId);

        if (!$player->is_on_market) {
            throw new Exception("This player is not on Market");
        }

        $team = Team::where('user_id', $userId)->first();

        if (!$team) {
            throw new Exception("User does not have a team");
        }

        if ($team->team_budget < $bidAmount) {
            throw new Exception("Bid cannot be less than $bidAmount");
        }

        $highestBid = Market::where('player_id', $playerId)
            ->orderBy('current_bid_amount', 'desc')
            ->first();

        $minimumBid = $highestBid ?
            $highestBid->current_bid_amount + 100 :
            $player->market_value;

        if ($bidAmount < $minimumBid) {
            throw new Exception("Bid amount must be at least $minimumBid");
        }

        $marketInput = Market::updateOrCreate(
            [
                'player_id' => $playerId,
                'user_id' => $userId,
            ],
            [
              'current_bid_amount' => $bidAmount,
              'bidding_end_date' => Carbon::now()->addDays(1),
            ],
        );

        event(new BidPlaced($marketInput));

        return $marketInput;
    }

    /**
     * @throws \Throwable
     */
    public function finalizeTransfer(int $marketId, int $teamId)
    {
        return DB::transaction(/**
         * @throws Exception
         */ function () use ($marketId, $teamId) {
            $marketInput = Market::findOrFail($marketId);
            $player = Player::findOrFail($marketInput->player_id);
            $team = Team::findOrFail($teamId);

            if (Carbon::now()->lt($marketInput->bidding_end_date)) {
                throw new Exception('Bidding is not over yet');
            }

            $highestBid = Market::where('player_id', $player->getKey())
                ->orderBy('current_bid_amount', 'desc')
                ->first();

            if ($marketInput->getKey() !== $highestBid->getKey()) {
                throw new Exception('This is not the highest bid');
            }

            $team->team_budget -= $marketInput->current_bid_amount;
            $team->save();

            $player->team_id = $teamId;
            $player->is_on_market = false;
            $player->save();

            Market::where('player_id', $player->getKey())->delete();

            return $player;
        });
    }
}

