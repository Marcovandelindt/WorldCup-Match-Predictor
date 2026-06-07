<?php

namespace App\Providers;

use App\Models\FootballMatch;
use App\Services\Api\FootballDataClient;
use App\Services\Predictor;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FootballDataClient::class);
        $this->app->singleton(Predictor::class);
    }

    public function boot(): void
    {
        Route::model('match', FootballMatch::class);
    }
}
