<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('api_id')->unique();
            $table->string('name', 100);
            $table->string('short_name', 50)->nullable();
            $table->char('fifa_code', 3)->nullable();
            $table->unsignedInteger('fifa_ranking')->nullable();
            $table->string('confederation', 10)->nullable();
            $table->unsignedInteger('wc_appearances')->default(0);
            $table->string('wc_best_result', 50)->nullable();
            $table->decimal('avg_goals_scored_wc', 4, 2)->default(0);
            $table->decimal('avg_goals_conceded_wc', 4, 2)->default(0);
            $table->string('flag_emoji', 10)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
