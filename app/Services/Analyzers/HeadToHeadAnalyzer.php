<?php

namespace App\Services\Analyzers;

use App\Models\EloRating;
use App\Models\TeamH2hMatch;

class HeadToHeadAnalyzer
{
    public function calculate(int $homeTeamId, int $awayTeamId): array
    {
        $wcAvg = (float) config('services.football_data.wc_average_goals', 1.30);

        $matches = TeamH2hMatch::where(function ($q) use ($homeTeamId, $awayTeamId) {
                $q->where('home_team_id', $homeTeamId)
                  ->where('away_team_id', $awayTeamId);
            })
            ->orWhere(function ($q) use ($homeTeamId, $awayTeamId) {
                $q->where('home_team_id', $awayTeamId)
                  ->where('away_team_id', $homeTeamId);
            })
            ->orderByDesc('match_date')
            ->limit(10)
            ->get();

        if ($matches->isEmpty()) {
            throw new \RuntimeException(
                "Geen H2H data gevonden voor teams {$homeTeamId} vs {$awayTeamId}. Draai eerst wk:import-team-data."
            );
        }

        $eloAvailable = EloRating::exists();

        $homeScored = [];
        $awayScored = [];
        $weights    = [];

        foreach ($matches as $match) {
            if ($match->home_team_id === $homeTeamId) {
                $homeScored[] = $match->home_score;
                $awayScored[] = $match->away_score;
            } else {
                $homeScored[] = $match->away_score;
                $awayScored[] = $match->home_score;
            }

            // Weeg op basis van hoe recent de wedstrijd is (meer recent = zwaarder)
            $yearsAgo  = max(0, (int) now()->diffInYears($match->match_date));
            $weights[] = $eloAvailable
                ? max(0.3, pow(0.88, $yearsAgo))
                : 1.0;
        }

        $totalWeight     = array_sum($weights);
        $avgHome         = array_sum(array_map(fn ($g, $w) => $g * $w, $homeScored, $weights)) / $totalWeight;
        $avgAway         = array_sum(array_map(fn ($g, $w) => $g * $w, $awayScored, $weights)) / $totalWeight;

        return [
            'attack_strength_home'  => $avgHome / $wcAvg,
            'defense_weakness_home' => $avgAway / $wcAvg,
            'attack_strength_away'  => $avgAway / $wcAvg,
            'defense_weakness_away' => $avgHome / $wcAvg,
            'matches_analyzed'      => $matches->count(),
        ];
    }
}
