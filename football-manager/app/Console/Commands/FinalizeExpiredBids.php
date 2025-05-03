<?php

namespace App\Console\Commands;

use App\Models\Market;
use App\Services\TransactionService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Symfony\Component\Console\Command\Command as CommandAlias;

class FinalizeExpiredBids extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'market:finalize-bids';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Finalize bids that have expired';

    protected $transactionService;

    public function __construct(TransactionService $transactionService) {
        parent::__construct();
        $this->transactionService = $transactionService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expiredBids = Market::whereDate('bidding_end_date', '<', Carbon::now())
            ->orderBy('player_id')
            ->orderBy('current_bid_amount', 'desc')
            ->get()
            ->groupBy('player_id');

        $count = 0;

        foreach ($expiredBids as $playerId => $bids) {
            if ($bids->count() > 0) {
                $highestBid = $bids->first();

                try {
                    $this->transactionService->finalizeTransfer(
                        $highestBid->geyKey(),
                        $highestBid->user->team->getKey()
                    );

                    $this->info("Transfer finalized for player ID: {$playerId} to team: {$highestBid->user->team->name}");
                    $count++;
                } catch (Exception $e) {
                    $this->error("Failed to finalize transfer for player ID: $playerId: {$e->getMessage()}");
                }
            }
        }

        $this->info("Finalized $count transfers");

        return CommandAlias::SUCCESS;
    }
}
