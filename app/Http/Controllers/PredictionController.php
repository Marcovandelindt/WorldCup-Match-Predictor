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
        $allMatches = FootballMatch::whereHas('prediction')
            ->with(['homeTeam', 'awayTeam', 'prediction', 'accuracy'])
            ->orderBy('match_date')
            ->get();

        // Build ordered sections: one per group (A–L), then knockout rounds
        $groupKeys = $allMatches
            ->where('stage', 'GROUP')
            ->pluck('group_name')
            ->unique()->sort()->values();

        $knockoutKeys = ['R16', 'QF', 'SF', 'THIRD', 'FINAL'];

        $sections = [];

        foreach ($groupKeys as $group) {
            $letter = str_replace('GROUP_', '', $group);
            $sections[$group] = [
                'label'   => 'Groep ' . $letter,
                'matches' => $allMatches->where('stage', 'GROUP')->where('group_name', $group)->values(),
            ];
        }

        $knockoutLabels = ['R16' => 'Achtste finales', 'QF' => 'Kwartfinale', 'SF' => 'Halve finale', 'THIRD' => 'Derde plaats', 'FINAL' => 'Finale'];
        foreach ($knockoutKeys as $key) {
            $matches = $allMatches->where('stage', $key)->values();
            if ($matches->isNotEmpty()) {
                $sections[$key] = ['label' => $knockoutLabels[$key], 'matches' => $matches];
            }
        }

        $totalPredicted = FootballMatch::whereHas('prediction')->count();
        $totalPlayed    = FootballMatch::whereHas('accuracy')->count();
        $exactCount     = PredictionAccuracy::where('exact_score', true)->count();
        $winnerCount    = PredictionAccuracy::where('correct_winner', true)->count();
        $totalPoints    = PredictionAccuracy::sum('points_earned');

        $stats = compact('totalPredicted', 'totalPlayed', 'exactCount', 'winnerCount', 'totalPoints');

        return view('predictions.index', compact('sections', 'stats', 'totalPoints'));
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
