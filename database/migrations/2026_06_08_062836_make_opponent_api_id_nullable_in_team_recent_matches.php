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
        Schema::table('team_recent_matches', function (Blueprint $table) {
            $table->unsignedInteger('opponent_api_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('team_recent_matches', function (Blueprint $table) {
            $table->unsignedInteger('opponent_api_id')->nullable(false)->change();
        });
    }
};
