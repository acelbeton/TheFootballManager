<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('player_performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('players');
            $table->foreignId('match_id')->constrained('game_matches');
            $table->integer('goals_scored')->default(0);
            $table->integer('assists')->default(0);
            $table->integer('rating')->default(0);
            $table->integer('minutes_played')->default(0);
            $table->timestamps();

            $table->index(['player_id', 'match_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_performances');
    }
};
