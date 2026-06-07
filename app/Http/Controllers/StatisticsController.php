<?php

namespace App\Http\Controllers;

use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Models\PredictionAccuracy;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    public function index()
    {
        $totalPoints  = PredictionAccuracy::sum('points_earned');
        $totalPlayed  = PredictionAccuracy::count();
        $exactCount   = PredictionAccuracy::where('exact_score', true)->count();
        $winnerCount  = PredictionAccuracy::where('correct_winner', true)->count();

        // Points per matchday (group by calendar date)
        $rawByDay = PredictionAccuracy::select(
            DB::raw('DATE(evaluated_at) as day'),
            DB::raw('SUM(points_earned) as points')
        )
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $cumulative = 0;
        $pointsPerMatchday = $rawByDay->values()->map(function ($row, $i) use (&$cumulative) {
            $cumulative += $row->points;
            return [
                'label'      => 'SD ' . ($i + 1),
                'points'     => (int) $row->points,
                'cumulative' => $cumulative,
            ];
        })->values()->toArray();

        $matchdayCount = count($pointsPerMatchday);

        $bestMatchday = !empty($pointsPerMatchday)
            ? collect($pointsPerMatchday)->sortByDesc('points')->first()
            : ['points' => 0, 'label' => '–'];

        // Longest exact streak
        $accuracies         = PredictionAccuracy::orderBy('evaluated_at')->pluck('exact_score')->toArray();
        $longestExactStreak = $this->longestStreak($accuracies);

        // Recent trend (last 4 matchdays vs previous 4)
        $recentTrend = $this->recentTrend($pointsPerMatchday);

        // Accuracy by stage
        $stageConfig = [
            ['key' => 'GROUP', 'label' => 'Groepsfase'],
            ['key' => 'R16',   'label' => 'Achtste F.'],
            ['key' => 'QF',    'label' => 'Kwartfinale'],
            ['key' => 'SF',    'label' => 'Halve finale'],
            ['key' => 'FINAL', 'label' => 'Finale'],
        ];

        $accuracyByStage = array_map(function ($cfg) {
            $matchIds = FootballMatch::where('stage', $cfg['key'])->pluck('id');
            $accs     = PredictionAccuracy::whereIn('match_id', $matchIds)->get();

            $total   = $accs->count();
            $exact   = $accs->where('exact_score', true)->count();
            $winner  = $accs->where('correct_winner', true)->count();

            return [
                'label'      => $cfg['label'],
                'total'      => $total,
                'exact_pct'  => $total > 0 ? round($exact / $total * 100) : 0,
                'winner_pct' => $total > 0 ? round($winner / $total * 100) : 0,
            ];
        }, $stageConfig);

        // Top predicted scorelines
        $topPredicted = Prediction::select(
            DB::raw("CONCAT(predicted_home, '-', predicted_away) as score"),
            DB::raw('COUNT(*) as count')
        )
            ->groupBy('score')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'score' => $r->score,
                'count' => $r->count,
                'pct'   => $totalPlayed > 0 ? round($r->count / $totalPlayed * 100) : 0,
            ])
            ->toArray();

        // Top actual scorelines
        $topActual = PredictionAccuracy::select(
            DB::raw("CONCAT(actual_home, '-', actual_away) as score"),
            DB::raw('COUNT(*) as count')
        )
            ->groupBy('score')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'score' => $r->score,
                'count' => $r->count,
                'pct'   => $totalPlayed > 0 ? round($r->count / $totalPlayed * 100) : 0,
            ])
            ->toArray();

        return view('statistics.index', compact(
            'totalPoints', 'totalPlayed', 'exactCount', 'winnerCount',
            'pointsPerMatchday', 'matchdayCount', 'bestMatchday',
            'longestExactStreak', 'recentTrend',
            'accuracyByStage', 'topPredicted', 'topActual'
        ));
    }

    private function longestStreak(array $results): int
    {
        $max = $current = 0;
        foreach ($results as $r) {
            $current = $r ? $current + 1 : 0;
            $max     = max($max, $current);
        }
        return $max;
    }

    private function recentTrend(array $pointsPerMatchday): string
    {
        if (count($pointsPerMatchday) < 2) return '+0%';

        $all      = array_column($pointsPerMatchday, 'points');
        $recent   = array_slice($all, -4);
        $previous = array_slice($all, -8, 4);

        $sumRecent   = array_sum($recent);
        $sumPrevious = array_sum($previous);

        if ($sumPrevious === 0) return '+0%';

        $trend = round(($sumRecent - $sumPrevious) / $sumPrevious * 100);
        return ($trend >= 0 ? '+' : '') . $trend . '%';
    }
}
