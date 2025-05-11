<?php

namespace App\Console\Commands;

use App\Services\WeeklyScheduleService;
use Illuminate\Console\Command;

class ProcessLeagueWeek extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'league:process-week';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process all leagues for the current week';
    protected $weeklyScheduleService;

    public function __construct(WeeklyScheduleService $weeklyScheduleService)
    {
        parent::__construct();
        $this->weeklyScheduleService = $weeklyScheduleService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting weekly league processing...');

        $this->weeklyScheduleService->processAllLeagues();

        $this->info('Weekly league processing completed successfully.');

        return 0;
    }
}
