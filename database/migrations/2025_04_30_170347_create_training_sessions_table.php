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
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('id', true)->unique();
            $table->enum('type', ['TEAM', 'INDIVIDUAL']);
            $table->foreignId('user_id')->constrained();
            $table->foreignId('team_id')->constrained();
            $table->json('participants')->nullable()->comment('Storing players for individual training');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_sessions');
    }
};
