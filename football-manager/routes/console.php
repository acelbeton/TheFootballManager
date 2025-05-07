<?php

use App\Console\Commands\FinalizeExpiredBids;
use App\Console\Commands\ProcessLeagues;
use App\Console\Commands\StartScheduledMatches;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Schedule::command(FinalizeExpiredBids::class)
    ->hourly();

Schedule::command(StartScheduledMatches::class)->everyMinute();

Schedule::command(ProcessLeagues::class)->hourly();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();
