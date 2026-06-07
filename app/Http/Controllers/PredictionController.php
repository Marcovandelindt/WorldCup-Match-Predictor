<?php

namespace App\Http\Controllers;

use App\Models\FootballMatch;
use App\Models\PredictionAccuracy;
use App\Services\Predictor;

class PredictionController extends Controller
{
    public function __construct(private Predictor $predictor) {}

    public function show(FootballMatch $match)
    {
        $prediction  = $match->prediction;
        $totalPoints = PredictionAccuracy::sum('points_earned');

        return view('predictions.show', compact('match', 'prediction', 'totalPoints'));
    }

    public function generate(FootballMatch $match)
    {
        $result = $this->predictor->predict($match);
        $match->refresh()->load(['prediction', 'homeTeam', 'awayTeam']);
        $prediction  = $match->prediction;
        $totalPoints = PredictionAccuracy::sum('points_earned');

        return view('predictions.show', compact('match', 'prediction', 'totalPoints'));
    }
}
