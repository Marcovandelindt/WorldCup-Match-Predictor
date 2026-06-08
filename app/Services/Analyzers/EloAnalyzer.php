<?php

namespace App\Services\Analyzers;

use App\Models\EloRating;

class EloAnalyzer
{
    // DB team name => Kaggle name (omgekeerde van de import-mapping)
    private array $nameMap = [
        'United States'      => 'USA',
        'Iran'               => 'IR Iran',
        'Congo DR'           => 'DR Congo',
        'Cape Verde Islands' => 'Cape Verde',
        'Czechia'            => 'Czech Republic',
        'Bosnia-Herzegovina' => 'Bosnia and Herzegovina',
    ];

    public function getRating(string $teamName): float
    {
        $rating = EloRating::where('team_name', $teamName)->value('rating');
        if ($rating !== null) return (float) $rating;

        $kaggleName = $this->nameMap[$teamName] ?? null;
        if ($kaggleName) {
            $rating = EloRating::where('team_name', $kaggleName)->value('rating');
            if ($rating !== null) return (float) $rating;
        }

        return 1500.0;
    }

    public function calculate(string $homeTeamName, string $awayTeamName): array
    {
        $homeElo = $this->getRating($homeTeamName);
        $awayElo = $this->getRating($awayTeamName);

        $diff   = $homeElo - $awayElo;
        $factor = 1 + ($diff / 1000);
        $factor = max(0.7, min(1.3, $factor));

        return [
            'home_elo'           => round($homeElo, 1),
            'away_elo'           => round($awayElo, 1),
            'lambda_home_factor' => $factor,
            'lambda_away_factor' => 1 / $factor,
        ];
    }
}
