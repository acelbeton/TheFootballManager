<?php

namespace App\Http\Controllers;

use App\Services\TransactionService;
use Illuminate\Http\Request;

class MarketController extends Controller
{
    protected $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    public function placeBid(Request $request)
    {
        $validated = $request->validate([
            'player_id' => 'required|exists:players,id',
            'user_id' => 'required|exists:users,id',
            'bid_amount' => 'required|numeric|min:1',
        ]);

        $marketEntry = $this->transactionService->placeBid(
            $validated['player_id'],
            $validated['user_id'],
            $validated['bid_amount']
        );

        return response()->json(['message' => 'Bid placed successfully', 'market_entry' => $marketEntry]);
    }

    public function finalizeTransfer(Request $request)
    {
        $validated = $request->validate([
            'market_id' => 'required|exists:market,id',
            'team_id' => 'required|exists:teams,id',
        ]);

        $player = $this->transactionService->finalizeTransfer(
            $validated['market_id'],
            $validated['team_id']
        );

        return response()->json(['message' => 'Transfer finalized', 'player' => $player]);
    }
}
