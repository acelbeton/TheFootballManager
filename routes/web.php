<?php

use App\Http\Controllers\MarketController;
use App\Livewire\Auth\Logout;
use App\Livewire\Dashboard;
use App\Livewire\LoginForm;
use App\Livewire\MatchViewer;
use App\Livewire\PlayerMarket;
use App\Livewire\RegistrationForm;
use App\Livewire\Team\CreateTeam;
use App\Livewire\TeamManagement;
use App\Livewire\TeamSelection;
use App\Livewire\TrainingDashboard;
use App\Livewire\Welcome;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/register', RegistrationForm::class)->name('register');
    Route::get('/login', LoginForm::class)->name('login');
});

Broadcast::routes(['middleware' => ['auth']]);

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    Route::get('/create-team', CreateTeam::class)->name('create-team');

    Route::get('/change-team', TeamSelection::class)->name('change-team');

    Route::get('/team-training', TrainingDashboard::class)->name('team-training');

    Route::post('/logout', Logout::class)->name('logout');

    // Market
    Route::get('/market', PlayerMarket::class)->name('market');
    Route::post('/market/bid', [MarketController::class, 'placeBid'])->name('market.bid');
    Route::post('/market/finalize', [MarketController::class, 'finalizeTransfer'])->name('market.finalize');

    Route::get('/team-management', TeamManagement::class)->name('team-management');

    Route::get('/matches/{matchId}', MatchViewer::class)->name('match.view');
});

Route::get('/', Welcome::class)->name('welcome');
