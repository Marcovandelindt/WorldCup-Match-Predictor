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
            throw new \RuntimeException(
                "Geen form data gevonden voor team ID {$teamId}. Draai eerst wk:import-team-data."
            );
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
