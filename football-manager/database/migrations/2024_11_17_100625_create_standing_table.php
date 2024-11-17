<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('standing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained('league');
            $table->foreignId('team_id')->constrained('team');
            $table->integer('goals_scored')->default(0);
            $table->integer('goals_conceded')->default(0);
            $table->integer('points')->default(0);
            $table->integer('matches_played')->default(0);
            $table->integer('matches_won')->default(0);
            $table->integer('matches_drawn')->default(0);
            $table->integer('matches_lost')->default(0);
            $table->timestamps();

            $table->index(['league_id', 'team_id','points']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('standing');
    }
};
