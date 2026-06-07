<?php

namespace App\Http\Controllers;

use App\Models\FootballMatch;
use App\Models\PredictionAccuracy;

class DashboardController extends Controller
{
    public function index()
    {
        $upcoming = FootballMatch::where('status', 'SCHEDULED')
            ->with(['homeTeam', 'awayTeam', 'prediction'])
            ->orderBy('match_date')
            ->get();

        $finished = FootballMatch::where('status', 'FINISHED')
            ->with(['homeTeam', 'awayTeam', 'prediction', 'accuracy'])
            ->orderByDesc('match_date')
            ->get();

        $total        = PredictionAccuracy::count();
        $exactCount   = PredictionAccuracy::where('exact_score', true)->count();
        $winnerCount  = PredictionAccuracy::where('correct_winner', true)->count();
        $totalPoints  = PredictionAccuracy::sum('points_earned');

        $stats = [
            'total'           => $total,
            'total_predicted' => $upcoming->count() + $finished->count(),
            'upcoming'        => $upcoming->count(),
            'exact_count'     => $exactCount,
            'winner_count'    => $winnerCount,
            'total_points'    => $totalPoints,
        ];

        $stageLabels = [
            'GROUP' => 'Groepsfase',
            'R16'   => 'Achtste finales',
            'QF'    => 'Kwartfinale',
            'SF'    => 'Halve finale',
            'THIRD' => 'Derde Plaats',
            'FINAL' => 'Finale',
        ];

        $currentStage = $upcoming->first()?->stage ?? 'GROUP';
        $currentStageLabel = $stageLabels[$currentStage] ?? $currentStage;

        return view('dashboard.index', compact('upcoming', 'finished', 'stats', 'currentStageLabel', 'totalPoints'));
    }
}
