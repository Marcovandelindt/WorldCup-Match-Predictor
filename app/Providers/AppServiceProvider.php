<?php

namespace App\Providers;

use App\Models\FootballMatch;
use App\Models\Team;
use App\Services\Api\FootballDataClient;
use App\Services\Predictor;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
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

        View::composer('*', function ($view) {
            $showImportNotice = Team::exists()
                && ! FootballMatch::whereIn('stage', ['R16', 'QF', 'SF', 'THIRD', 'FINAL'])->exists();

            $view->with('showImportNotice', $showImportNotice);
        });
    }
}
