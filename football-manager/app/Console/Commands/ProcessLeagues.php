<?php

namespace App\Console\Commands;

use App\Models\League;
use App\Services\LeagueManagerService;
use Exception;
use Illuminate\Console\Command;
use Log;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ProcessLeagues extends Command
{
    protected $signature = 'app:process-leagues';
    protected $description = 'Process all leagues and simulate pending AI matches';

    public function handle(LeagueManagerService $leagueManager)
    {
        $this->info('Starting league processing...');

        $leagues = League::all();
        $processedCount = 0;

        foreach ($leagues as $league) {
            try {
                $this->info("Processing league: {$league->name}");
                $result = $leagueManager->processLeague($league);

                if (!empty($result['queuedMatches'])) {
                    $this->info("- Queued " . count($result['queuedMatches']) . " AI matches for simulation");
                }

                $processedCount++;
            } catch (Exception $e) {
                $this->error("Failed to process league {$league->name}: {$e->getMessage()}");
                Log::error('Error processing league: ' . $e->getMessage());
            }
        }

        $this->info("Completed processing {$processedCount} leagues.");

        return CommandAlias::SUCCESS;
    }
}
