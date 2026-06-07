<?php

namespace App\Services\Analyzers;

use App\Services\Api\FootballDataClient;

class HeadToHeadAnalyzer
{
    public function calculate(int $matchApiId, int $homeApiId, FootballDataClient $client): array
    {
        $wcAvg   = (float) config('services.football_data.wc_average_goals', 1.30);
        $data    = $client->getHeadToHead($matchApiId);
        $matches = $data['matches'] ?? [];

        if (empty($matches)) {
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
            $isHome       = $match['homeTeam']['id'] === $homeApiId;
            $homeScored[] = $isHome ? $match['score']['fullTime']['home'] : $match['score']['fullTime']['away'];
            $awayScored[] = $isHome ? $match['score']['fullTime']['away'] : $match['score']['fullTime']['home'];
        }

        $avgHome = array_sum($homeScored) / count($homeScored);
        $avgAway = array_sum($awayScored) / count($awayScored);

        return [
            'attack_strength_home'  => $avgHome / $wcAvg,
            'defense_weakness_home' => $avgAway / $wcAvg,
            'attack_strength_away'  => $avgAway / $wcAvg,
            'defense_weakness_away' => $avgHome / $wcAvg,
            'matches_analyzed'      => count($matches),
        ];
    }
}
