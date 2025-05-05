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
        Schema::create('team_lineups', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('match_id')->nullable()->constrained('game_matches')->onDelete('cascade');
            $table->foreignId('formation_id')->constrained('formations')->onDelete('cascade');
            $table->enum('tactic', ['ATTACK_MODE', 'DEFEND_MODE', 'DEFAULT_MODE'])->default('DEFAULT_MODE');
//            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index(['team_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_lineups');
    }
};
