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
        Schema::table('game_matches', function (Blueprint $table) {
            $table->integer('home_possession')->default(50);
            $table->integer('away_possession')->default(50);
            $table->integer('home_shots')->default(0);
            $table->integer('away_shots')->default(0);
            $table->integer('home_shots_on_target')->default(0);
            $table->integer('away_shots_on_target')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropColumn([
                'home_possession',
                'away_possession',
                'home_shots',
                'away_shots',
                'home_shots_on_target',
                'away_shots_on_target',
            ]);
        });
    }
};
