<?php

namespace App\Console\Commands;

use App\Http\Enums\PlayerPosition;
use App\Models\Market;
use App\Models\Player;
use App\Models\Team;
use App\Services\TransactionService;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ManageMarket extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'market:manage {--generate-count=3}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process expired bids and generate new market players';

    protected $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        parent::__construct();
        $this->transactionService = $transactionService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->handleExpiredBids();
        $count = (int)$this->option('generate-count');
        $this->generateMarketPlayers($count);

        return 0;
    }

    private function handleExpiredBids(): void
    {
        $this->info("Processing expired market bids...");

        $expiredBids = Market::whereDate('bidding_end_date', '<', Carbon::now())
            ->orderBy('player_id')
            ->orderBy('current_bid_amount', 'desc')
            ->get()
            ->groupBy('player_id');

        $finalized = 0;
        $reset = 0;

        foreach ($expiredBids as $playerId => $bids) {
            $player = Player::find($playerId);
            if (!$player) continue;

            if ($bids->count() > 0 && $bids->first()->current_bid_amount > 0) {
                $highestBid = $bids->first();

                try {
                    $this->transactionService->finalizeTransfer(
                        $highestBid->getKey(),
                        $highestBid->user->team->getKey()
                    );

                    $finalized++;
                    $this->info("Transfer finalized for player ID: {$playerId}");
                } catch (Exception $e) {
                    $this->error("Failed to finalize transfer for player ID: {$playerId}: {$e->getMessage()}");
                } catch (Throwable $e) {
                    $this->error("Throwable: {$playerId}: {$e->getMessage()}");
                }
            } else {
                try {
                    $this->resetPlayerBidding($player);
                    $reset++;
                    $this->info("Reset bidding for player ID: {$playerId}");
                } catch (Exception $e) {
                    $this->error("Error resetting bidding for player ID: {$playerId}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Finalized {$finalized} transfers and reset bidding for {$reset} players");
    }

    private function resetPlayerBidding(Player $player): void
    {
        Market::where('player_id', $player->getKey())->delete();

        if (rand(1, 100) > 50) {
            $player->market_value = (int)($player->market_value * 0.95);
            $player->save();
        }
    }

    private function generateMarketPlayers(int $count): void
    {
        $currentMarketCount = Player::where('is_on_market', true)->count();
        if ($currentMarketCount >= 15) {
            $this->info("Market already has {$currentMarketCount} players. Skipping generation.");
            return;
        }

        $this->info("Generating {$count} players for the market...");
        $generated = 0;

        try {
            $positions = [];
            foreach (PlayerPosition::cases() as $position) {
                $positions[] = $position->value;
            }

            for ($i = 0; $i < $count; $i++) {
                $position = $positions[array_rand($positions)];

                $player = $this->createMarketPlayer($position);
                $generated++;

                $this->info("Created player: {$player->name} ({$player->position}) - Rating: {$player->rating}");
            }

            $this->info("Successfully generated {$generated} players for the market");
        } catch (Exception $e) {
            $this->error("Error generating market players: " . $e->getMessage());
        }
    }

    private function createMarketPlayer(string $position): Player
    {
        $player = new Player();
        $player->name = $this->generatePlayerName();
        $player->position = $position;
        $player->condition = rand(80, 100);
        $player->is_on_market = true;
        $player->is_injured = false;

        $stats = $this->generateStats($position);

        $totalStats = array_sum($stats);
        $player->rating = (int)($totalStats / count($stats));

        $player->market_value = $this->calculateMarketValue($player->rating);

        DB::transaction(function() use ($player, $stats) {
            $player->save();
            $player->statistics()->create($stats);
        });

        return $player;
    }

    private function generateStats(string $position): array
    {
        $baseStats = [
            'attacking' => rand(40, 60),
            'defending' => rand(40, 60),
            'stamina' => rand(40, 60),
            'technical_skills' => rand(40, 60),
            'speed' => rand(40, 60),
            'tactical_sense' => rand(40, 60),
        ];

        switch ($position) {
            case PlayerPosition::GOALKEEPER->value:
                $baseStats['defending'] += rand(10, 20);
                $baseStats['attacking'] -= rand(5, 15);
                break;

            case PlayerPosition::CENTRE_BACK->value:
            case PlayerPosition::FULLBACK->value:
                $baseStats['defending'] += rand(10, 20);
                $baseStats['stamina'] += rand(5, 10);
                break;

            case PlayerPosition::MIDFIELDER->value:
                $baseStats['stamina'] += rand(5, 15);
                $baseStats['technical_skills'] += rand(5, 15);
                $baseStats['tactical_sense'] += rand(5, 15);
                break;

            case PlayerPosition::WINGER->value:
                $baseStats['speed'] += rand(10, 20);
                $baseStats['technical_skills'] += rand(5, 15);
                break;

            case PlayerPosition::STRIKER->value:
                $baseStats['attacking'] += rand(10, 20);
                $baseStats['technical_skills'] += rand(5, 15);
                break;
        }

        foreach ($baseStats as $key => $value) {
            $baseStats[$key] = max(1, min(100, $value));
        }

        return $baseStats;
    }

    private function calculateMarketValue(int $rating): int
    {
        $baseValue = 1000 * pow(1.1, $rating - 50);
        $randomFactor = rand(90, 110) / 100;

        return (int)round($baseValue * $randomFactor);
    }

    private function generatePlayerName(): string
    {
        $firstNames = ['John', 'James', 'David', 'Michael', 'Robert', 'Carlos', 'Juan',
            'Francesco', 'Marco', 'Stefan', 'Hans', 'Pierre', 'Gábor', 'István', 'Péter'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Rodriguez',
            'Rossi', 'Ferrari', 'Müller', 'Schmidt', 'Dubois', 'Nagy', 'Kovács', 'Tóth'];

        $firstName = $firstNames[array_rand($firstNames)];
        $lastName = $lastNames[array_rand($lastNames)];

        return "$firstName $lastName";
    }
}
