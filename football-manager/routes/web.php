<?php

use App\Http\Controllers\MarketController;
use App\Livewire\Auth\Logout;
use App\Livewire\Dashboard;
use App\Livewire\LoginForm;
use App\Livewire\PlayerMarket;
use App\Livewire\Players;
use App\Livewire\RegistrationForm;
use App\Livewire\Team\CreateTeam;
use App\Livewire\Teams;
use App\Livewire\TeamSelection;
use App\Livewire\TrainingDashboard;
use App\Livewire\Welcome;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/register', RegistrationForm::class)->name('register');
    Route::get('/login', LoginForm::class)->name('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    Route::get('/teams', Teams::class)->name('teams.index');
    Route::get('/players', Players::class)->name('players.index');

    Route::get('/create-team', CreateTeam::class)->name('create-team');

    Route::get('/change-team', TeamSelection::class)->name('change-team');

    Route::get('/team-training', TrainingDashboard::class)->name('team-training');

    Route::post('/logout', Logout::class)->name('logout');

    // Market
    Route::get('/market', PlayerMarket::class)->name('market');
    Route::post('/market/bid', [MarketController::class, 'placeBid'])->name('market.bid');
    Route::post('/market/finalize', [MarketController::class, 'finalizeTransfer'])->name('market.finalize');
});

Route::get('/', Welcome::class)->name('welcome');
