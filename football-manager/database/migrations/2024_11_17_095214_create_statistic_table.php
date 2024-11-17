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
        Schema::create('statistic', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('player');
            $table->integer('attacking');
            $table->integer('defending');
            $table->integer('stamina');
            $table->integer('technical_skills');
            $table->integer('speed');
            $table->integer('tactical_sense');
            $table->timestamps();

            DB::statement('ALTER TABLE statistic ADD CONSTRAINT chk_attacking CHECK (attacking BETWEEN 1 and 100)');
            DB::statement('ALTER TABLE statistic ADD CONSTRAINT chk_defending CHECK (defending BETWEEN 1 and 100)');
            DB::statement('ALTER TABLE statistic ADD CONSTRAINT chk_stamina CHECK (stamina BETWEEN 1 and 100)');
            DB::statement('ALTER TABLE statistic ADD CONSTRAINT chk_technical_skills CHECK (technical_skills BETWEEN 1 and 100)');
            DB::statement('ALTER TABLE statistic ADD CONSTRAINT chk_speed CHECK (speed BETWEEN 1 and 100)');
            DB::statement('ALTER TABLE statistic ADD CONSTRAINT chk_tactical_sense CHECK (tactical_sense BETWEEN 1 and 100)');

            $table->index(['player_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statistic');
    }
};
