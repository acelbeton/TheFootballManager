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
        Schema::create('lineup_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lineup_id')->constrained('team_lineups')->onDelete('cascade');
            $table->foreignId('player_id')->constrained('players');
            $table->enum('position', ['GOALKEEPER', 'CENTRE_BACK', 'FULLBACK', 'MIDFIELDER', 'WINGER', 'STRIKER']);
            $table->boolean('is_starter')->default(false);
            $table->integer('position_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lineup_players');
    }
};
