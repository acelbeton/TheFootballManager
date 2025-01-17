<?php

use App\Http\Controllers\TeamController;
use App\Livewire\Players;
use App\Livewire\TeamCreation;
use App\Livewire\Teams;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);

    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});


Route::middleware(['auth'])->group(function () {
    Route::get('/teams', Teams::class)->name('teams.index');
    Route::get('/players', Players::class)->name('players.index');
    Route::get('/create-team', TeamCreation::class)->name('team.create');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});


Route::get('/teams', Teams::class);

Route::get('/', function () {
    return view('welcome');
});
