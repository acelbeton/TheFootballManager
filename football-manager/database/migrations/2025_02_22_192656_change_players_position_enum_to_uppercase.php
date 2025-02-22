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
        Schema::table('players', function (Blueprint $table) {
            $table->enum('position', ['GOALKEEPER', 'CENTRE_BACK', 'FULLBACK', 'MIDFIELDER', 'WINGER', 'STRIKER'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('uppercase', function (Blueprint $table) {
            $table->enum('position', ['goalkeeper', 'centre-back', 'fullback', 'midfielder', 'winger', 'striker'])->change();
        });
    }
};
