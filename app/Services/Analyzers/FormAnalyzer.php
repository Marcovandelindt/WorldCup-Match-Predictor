<?php

namespace App\Services\Analyzers;

use App\Models\TeamRecentMatch;

class FormAnalyzer
{
    public function calculate(int $teamId): array
    {
        $wcAvg   = (float) config('services.football_data.wc_average_goals', 1.30);
        $matches = TeamRecentMatch::where('team_id', $teamId)
            ->orderByDesc('match_date')
            ->limit(10)
            ->get();

        if ($matches->isEmpty()) {
            return [
                'attack_strength'  => 1.0,
                'defense_weakness' => 1.0,
                'avg_scored'       => $wcAvg,
                'avg_conceded'     => $wcAvg,
                'matches_analyzed' => 0,
            ];
        }

        $avgScored   = $matches->avg('goals_scored');
        $avgConceded = $matches->avg('goals_conceded');

        return [
            'attack_strength'  => $avgScored   / $wcAvg,
            'defense_weakness' => $avgConceded / $wcAvg,
            'avg_scored'       => $avgScored,
            'avg_conceded'     => $avgConceded,
            'matches_analyzed' => $matches->count(),
        ];
    }
}
