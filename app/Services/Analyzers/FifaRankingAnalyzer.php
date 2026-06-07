<?php

namespace App\Services\Analyzers;

class FifaRankingAnalyzer
{
    public function calculate(int $homeRanking, int $awayRanking): array
    {
        $diff   = $awayRanking - $homeRanking;
        $factor = 1 + ($diff * 0.003);
        $factor = max(0.7, min(1.3, $factor));

        return [
            'lambda_home_factor' => $factor,
            'lambda_away_factor' => 1 / $factor,
        ];
    }
}
