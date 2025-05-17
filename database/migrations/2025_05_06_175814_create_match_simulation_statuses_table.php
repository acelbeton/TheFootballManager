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
        Schema::create('match_simulation_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('game_matches')->onDelete('cascade');
            $table->enum('status', ['QUEUED', 'IN_PROGRESS', 'COMPLETED', 'FAILED']);
            $table->string('job_id')->nullable();
            $table->integer('current_minute')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_simulation_statuses');
    }
};
