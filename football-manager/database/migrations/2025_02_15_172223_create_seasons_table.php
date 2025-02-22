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
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('open');
            $table->integer('prize_money_first');
            $table->integer('prize_money_second');
            $table->integer('prize_money_third');
            $table->integer('prize_money_other');
            $table->timestamps();

            $table->index(['league_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seasons');
    }
};
