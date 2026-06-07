<?php

namespace App\Http\Controllers;

use App\Models\FootballMatch;
use App\Models\PredictionAccuracy;

class ResultController extends Controller
{
    public function index()
    {
        $stages = ['GROUP', 'R16', 'QF', 'SF', 'THIRD', 'FINAL'];

        $matchesByStage = [];
        foreach ($stages as $stage) {
            $matchesByStage[$stage] = FootballMatch::where('stage', $stage)
                ->with(['homeTeam', 'awayTeam', 'prediction', 'accuracy'])
                ->orderBy('match_date')
                ->get();
        }

        $totalPoints = PredictionAccuracy::sum('points_earned');

        return view('results.index', compact('matchesByStage', 'totalPoints'));
    }
}
