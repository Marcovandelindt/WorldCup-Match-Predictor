<?php

namespace App\Services;

use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Services\Analyzers\FifaRankingAnalyzer;
use App\Services\Analyzers\FormAnalyzer;
use App\Services\Analyzers\HeadToHeadAnalyzer;
use App\Services\Analyzers\WorldCupHistoryAnalyzer;
use App\Services\Models\DixonColesModel;

class Predictor
{
    public function __construct(
        private FormAnalyzer            $form,
        private HeadToHeadAnalyzer      $h2h,
        private FifaRankingAnalyzer     $fifa,
        private WorldCupHistoryAnalyzer $wcHistory,
        private DixonColesModel         $dixonColes,
    ) {}

    public function predict(FootballMatch $match): array
    {
        $homeTeam = $match->homeTeam;
        $awayTeam = $match->awayTeam;
        $wcAvg    = (float) config('services.football_data.wc_average_goals', 1.30);

        $formHome = $this->form->calculate($homeTeam->id);
        $formAway = $this->form->calculate($awayTeam->id);
        $h2hData  = $this->h2h->calculate($homeTeam->id, $awayTeam->id);
        $fifaData = $this->fifa->calculate($homeTeam->fifa_ranking ?? 50, $awayTeam->fifa_ranking ?? 50);
        $wcData   = $this->wcHistory->calculate($homeTeam, $awayTeam);

        $lambdaHomeForm = $formHome['attack_strength'] * $formAway['defense_weakness'] * $wcAvg;
        $lambdaAwayForm = $formAway['attack_strength'] * $formHome['defense_weakness'] * $wcAvg;

        $lambdaHomeH2h  = $h2hData['attack_strength_home'] * $h2hData['defense_weakness_away'] * $wcAvg;
        $lambdaAwayH2h  = $h2hData['attack_strength_away'] * $h2hData['defense_weakness_home'] * $wcAvg;

        $lambdaHomeFifa = $lambdaHomeForm * $fifaData['lambda_home_factor'];
        $lambdaAwayFifa = $lambdaAwayForm * $fifaData['lambda_away_factor'];

        $lambdaHomeWc   = $wcData['attack_home'] * $wcData['defense_away'] * $wcAvg;
        $lambdaAwayWc   = $wcData['attack_away'] * $wcData['defense_home'] * $wcAvg;

        $lambdaHome = ($lambdaHomeForm * 0.40)
            + ($lambdaHomeH2h  * 0.30)
            + ($lambdaHomeFifa * 0.20)
            + ($lambdaHomeWc   * 0.10);

        $lambdaAway = ($lambdaAwayForm * 0.40)
            + ($lambdaAwayH2h  * 0.30)
            + ($lambdaAwayFifa * 0.20)
            + ($lambdaAwayWc   * 0.10);

        $scorelines = $this->dixonColes->predict($lambdaHome, $lambdaAway);
        $best       = $scorelines[0];

        Prediction::updateOrCreate(
            ['match_id' => $match->id],
            [
                'predicted_home' => $best['home'],
                'predicted_away' => $best['away'],
                'confidence_pct' => $best['probability'],
                'lambda_home'    => round($lambdaHome, 4),
                'lambda_away'    => round($lambdaAway, 4),
                'top_scorelines' => $scorelines,
                'generated_at'   => now(),
            ]
        );

        return [
            'match'       => $match,
            'home_team'   => $homeTeam,
            'away_team'   => $awayTeam,
            'prediction'  => $best,
            'scorelines'  => $scorelines,
            'lambda_home' => round($lambdaHome, 4),
            'lambda_away' => round($lambdaAway, 4),
        ];
    }
}
