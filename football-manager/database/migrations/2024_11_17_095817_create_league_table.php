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
        Schema::create('league', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('season');
            $table->integer('prize_money_first');
            $table->integer('prize_money_second');
            $table->integer('prize_money_third');
            $table->integer('prize_money_other');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('league');
    }
};
