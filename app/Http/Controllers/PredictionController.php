<?php

namespace App\Http\Controllers;

use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Models\PredictionAccuracy;
use App\Services\Predictor;

class PredictionController extends Controller
{
    public function __construct(private Predictor $predictor) {}

    public function index()
    {
        $stageOrder = ['GROUP', 'R16', 'QF', 'SF', 'THIRD', 'FINAL'];

        $matches = FootballMatch::whereHas('prediction')
            ->with(['homeTeam', 'awayTeam', 'prediction', 'accuracy'])
            ->orderBy('match_date')
            ->get()
            ->groupBy('stage');

        $matchesByStage = collect($stageOrder)->mapWithKeys(
            fn ($s) => [$s => $matches->get($s, collect())]
        );

        $totalPredicted = FootballMatch::whereHas('prediction')->count();
        $totalPlayed    = FootballMatch::whereHas('accuracy')->count();
        $exactCount     = PredictionAccuracy::where('exact_score', true)->count();
        $winnerCount    = PredictionAccuracy::where('correct_winner', true)->count();
        $totalPoints    = PredictionAccuracy::sum('points_earned');

        $stats = compact('totalPredicted', 'totalPlayed', 'exactCount', 'winnerCount', 'totalPoints');

        return view('predictions.index', compact('matchesByStage', 'stats', 'totalPoints'));
    }

    public function show(FootballMatch $match)
    {
        $prediction  = $match->prediction;
        $totalPoints = PredictionAccuracy::sum('points_earned');

        return view('predictions.show', compact('match', 'prediction', 'totalPoints'));
    }

    public function generate(FootballMatch $match)
    {
        try {
            $this->predictor->predict($match);
        } catch (\RuntimeException $e) {
            return redirect()->route('predict.show', $match)
                ->with('error', $e->getMessage());
        }

        $match->refresh()->load(['prediction', 'homeTeam', 'awayTeam']);
        $prediction  = $match->prediction;
        $totalPoints = PredictionAccuracy::sum('points_earned');

        return view('predictions.show', compact('match', 'prediction', 'totalPoints'));
    }
}
