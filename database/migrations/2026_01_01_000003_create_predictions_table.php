<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->unique()->constrained('matches');
            $table->unsignedInteger('predicted_home');
            $table->unsignedInteger('predicted_away');
            $table->decimal('confidence_pct', 5, 2)->nullable();
            $table->decimal('lambda_home', 6, 4)->nullable();
            $table->decimal('lambda_away', 6, 4)->nullable();
            $table->decimal('weight_form', 4, 2)->default(0.40);
            $table->decimal('weight_h2h', 4, 2)->default(0.30);
            $table->decimal('weight_fifa', 4, 2)->default(0.20);
            $table->decimal('weight_wc_history', 4, 2)->default(0.10);
            $table->json('top_scorelines')->nullable();
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};
