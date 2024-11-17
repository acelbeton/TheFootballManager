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
        Schema::create('player', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('rating')->default(0); // Statistic-bol jon ossze
            $table->foreignId('team_id')->nullable()->constrained('team')->onDelete('SET NULL');
            $table->foreignId('position_id')->constrained('position');
            $table->integer('market_value')->default(0);
            $table->boolean('is_on_market')->default(true);
            $table->integer('condition')->default(0);
            $table->boolean('is_injured')->default(false);
            $table->timestamps();

            $table->index(['team_id', 'position_id', 'is_on_market']);
            DB::statement('ALTER TABLE player ADD CONSTRAINT chk_rating CHECK (rating BETWEEN 1 and 100)');
            DB::statement('ALTER TABLE player ADD CONSTRAINT chk_condition CHECK (condition BETWEEN 1 and 100)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player');
    }
};
