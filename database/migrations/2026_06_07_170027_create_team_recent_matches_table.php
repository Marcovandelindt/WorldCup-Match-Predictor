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
        Schema::create('team_recent_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams');
            $table->unsignedInteger('opponent_api_id');
            $table->string('opponent_name', 100);
            $table->date('match_date');
            $table->unsignedInteger('goals_scored');
            $table->unsignedInteger('goals_conceded');
            $table->enum('result', ['WIN', 'DRAW', 'LOSS']);
            $table->string('competition', 100)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_recent_matches');
    }
};
