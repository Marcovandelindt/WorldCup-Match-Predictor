<?php

namespace App\Services\Analyzers;

use App\Services\Api\FootballDataClient;

class FormAnalyzer
{
    public function calculate(int $teamApiId, FootballDataClient $client): array
    {
        $wcAvg   = (float) config('services.football_data.wc_average_goals', 1.30);
        $data    = $client->getTeamMatches($teamApiId, 10);
        $matches = $data['matches'] ?? [];

        $goalsScored = $goalsConceded = [];

        foreach ($matches as $match) {
            $isHome          = $match['homeTeam']['id'] === $teamApiId;
            $goalsScored[]   = $isHome ? $match['score']['fullTime']['home'] : $match['score']['fullTime']['away'];
            $goalsConceded[] = $isHome ? $match['score']['fullTime']['away'] : $match['score']['fullTime']['home'];
        }

        $avgScored   = count($goalsScored)   > 0 ? array_sum($goalsScored)   / count($goalsScored)   : $wcAvg;
        $avgConceded = count($goalsConceded) > 0 ? array_sum($goalsConceded) / count($goalsConceded) : $wcAvg;

        return [
            'attack_strength'  => $avgScored   / $wcAvg,
            'defense_weakness' => $avgConceded / $wcAvg,
            'avg_scored'       => $avgScored,
            'avg_conceded'     => $avgConceded,
            'matches_analyzed' => count($matches),
        ];
    }
}
