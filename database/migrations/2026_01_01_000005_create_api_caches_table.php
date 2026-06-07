<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_caches', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key')->unique();
            $table->string('endpoint', 500);
            $table->longText('response_body');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_caches');
    }
};
