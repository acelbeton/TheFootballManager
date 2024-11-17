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
        Schema::create('match', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_team_id')->constrained('team');
            $table->foreignId('away_team_id')->constrained('team');
            $table->integer('home_team_score')->default(0);
            $table->integer('away_team_score')->default(0);
            $table->dateTime('match_date');
            $table->timestamps();

            $table->index(['home_team_id', 'away_team_id', 'match_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match');
    }
};
