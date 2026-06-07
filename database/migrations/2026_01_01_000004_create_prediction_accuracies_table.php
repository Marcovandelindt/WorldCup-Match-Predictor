<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prediction_accuracies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prediction_id')->unique()->constrained('predictions');
            $table->foreignId('match_id')->constrained('matches');
            $table->unsignedInteger('predicted_home');
            $table->unsignedInteger('predicted_away');
            $table->unsignedInteger('actual_home');
            $table->unsignedInteger('actual_away');
            $table->boolean('exact_score')->default(false);
            $table->boolean('correct_winner')->default(false);
            $table->boolean('correct_goal_diff')->default(false);
            $table->unsignedInteger('points_earned')->default(0);
            $table->timestamp('evaluated_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prediction_accuracies');
    }
};
