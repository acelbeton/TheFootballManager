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
        Schema::create('team', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('name')->unique();
            $table->enum('current_tactic', ['ATTACK_MODE', 'DEFEND_MODE', 'DEFAULT_MODE'])->default('DEFAULT_MODE');
            $table->unsignedTinyInteger('team_rating')->default(0);
            $table->integer('team_budget')->default(10000);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team');
    }
};
