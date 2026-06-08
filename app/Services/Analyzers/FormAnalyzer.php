<?php

namespace App\Services\Analyzers;

use App\Models\EloRating;
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

        $eloAvailable = EloRating::exists();

        if (! $eloAvailable) {
            $avgScored   = $matches->avg('goals_scored');
            $avgConceded = $matches->avg('goals_conceded');
        } else {
            $weightedScored   = 0.0;
            $weightedConceded = 0.0;
            $totalWeight      = 0.0;

            foreach ($matches as $match) {
                $opponentElo = EloRating::where('team_name', $match->opponent_name)->value('rating') ?? 1500.0;
                $weight      = max(0.3, $opponentElo / 1500);

                $weightedScored   += $match->goals_scored   * $weight;
                $weightedConceded += $match->goals_conceded * $weight;
                $totalWeight      += $weight;
            }

            $avgScored   = $weightedScored   / $totalWeight;
            $avgConceded = $weightedConceded / $totalWeight;
        }

        return [
            'attack_strength'  => $avgScored   / $wcAvg,
            'defense_weakness' => $avgConceded / $wcAvg,
            'avg_scored'       => $avgScored,
            'avg_conceded'     => $avgConceded,
            'matches_analyzed' => $matches->count(),
            'elo_weighted'     => $eloAvailable,
        ];
    }
}
