<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('api_id')->unique();
            $table->foreignId('home_team_id')->constrained('teams');
            $table->foreignId('away_team_id')->constrained('teams');
            $table->dateTime('match_date');
            $table->enum('stage', ['GROUP', 'R16', 'QF', 'SF', 'THIRD', 'FINAL']);
            $table->string('group_name', 10)->nullable();
            $table->string('venue', 150)->nullable();
            $table->enum('status', ['SCHEDULED', 'LIVE', 'FINISHED', 'POSTPONED'])->default('SCHEDULED');
            $table->unsignedInteger('home_score')->nullable();
            $table->unsignedInteger('away_score')->nullable();
            $table->unsignedInteger('home_score_ht')->nullable();
            $table->unsignedInteger('away_score_ht')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
