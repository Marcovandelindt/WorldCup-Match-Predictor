<?php

namespace App\Services\Analyzers;

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
            return [
                'attack_strength_home'  => 1.0,
                'defense_weakness_home' => 1.0,
                'attack_strength_away'  => 1.0,
                'defense_weakness_away' => 1.0,
                'matches_analyzed'      => 0,
            ];
        }

        $homeScored = $awayScored = [];

        foreach ($matches as $match) {
            if ($match->home_team_id === $homeTeamId) {
                $homeScored[] = $match->home_score;
                $awayScored[] = $match->away_score;
            } else {
                $homeScored[] = $match->away_score;
                $awayScored[] = $match->home_score;
            }
        }

        $avgHome = array_sum($homeScored) / count($homeScored);
        $avgAway = array_sum($awayScored) / count($awayScored);

        return [
            'attack_strength_home'  => $avgHome / $wcAvg,
            'defense_weakness_home' => $avgAway / $wcAvg,
            'attack_strength_away'  => $avgAway / $wcAvg,
            'defense_weakness_away' => $avgHome / $wcAvg,
            'matches_analyzed'      => $matches->count(),
        ];
    }
}
