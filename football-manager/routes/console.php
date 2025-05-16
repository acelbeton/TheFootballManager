<?php

use App\Console\Commands\FinalizeExpiredBids;
use App\Console\Commands\ProcessLeagues;
use App\Console\Commands\ProcessLeagueWeek;
use App\Console\Commands\StartScheduledMatches;
use App\Models\Player;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Schedule::command(StartScheduledMatches::class)->everyMinute();

Schedule::command(ProcessLeagueWeek::class)->daily();

Schedule::command('market:manage')->dailyAt('15:00');

Schedule::command('market:manage --generate-count=5')->weekends()->at('12:00');

Schedule::command('market:manage')
    ->dailyAt('09:00')
    ->when(function () {
        return Player::where('is_on_market', true)->count() < 5;
    });

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();
