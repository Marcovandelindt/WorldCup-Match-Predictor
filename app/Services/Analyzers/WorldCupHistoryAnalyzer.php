<?php

namespace App\Services\Analyzers;

use App\Models\Team;

class WorldCupHistoryAnalyzer
{
    public function calculate(Team $homeTeam, Team $awayTeam): array
    {
        $wcAvg = (float) config('services.football_data.wc_average_goals', 1.30);

        $homeAttack  = $homeTeam->avg_goals_scored_wc   > 0 ? $homeTeam->avg_goals_scored_wc   / $wcAvg : 1.0;
        $homeDefense = $homeTeam->avg_goals_conceded_wc > 0 ? $homeTeam->avg_goals_conceded_wc / $wcAvg : 1.0;
        $awayAttack  = $awayTeam->avg_goals_scored_wc   > 0 ? $awayTeam->avg_goals_scored_wc   / $wcAvg : 1.0;
        $awayDefense = $awayTeam->avg_goals_conceded_wc > 0 ? $awayTeam->avg_goals_conceded_wc / $wcAvg : 1.0;

        return [
            'attack_home'  => $homeAttack,
            'defense_home' => $homeDefense,
            'attack_away'  => $awayAttack,
            'defense_away' => $awayDefense,
        ];
    }
}
