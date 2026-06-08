<?php

namespace App\Services;

use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Models\TeamH2hMatch;
use App\Models\TeamRecentMatch;
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

        $hasFormHome = TeamRecentMatch::where('team_id', $homeTeam->id)->exists();
        $hasFormAway = TeamRecentMatch::where('team_id', $awayTeam->id)->exists();

        if (! $hasFormHome || ! $hasFormAway) {
            throw new \RuntimeException(
                "Geen form-data gevonden voor {$homeTeam->name} of {$awayTeam->name}. Draai eerst wk:import-historical-data."
            );
        }

        $hasH2h = TeamH2hMatch::where(function ($q) use ($homeTeam, $awayTeam) {
                $q->where('home_team_id', $homeTeam->id)->where('away_team_id', $awayTeam->id);
            })->orWhere(function ($q) use ($homeTeam, $awayTeam) {
                $q->where('home_team_id', $awayTeam->id)->where('away_team_id', $homeTeam->id);
            })->exists();

        $formHome = $this->form->calculate($homeTeam->id);
        $formAway = $this->form->calculate($awayTeam->id);
        $fifaData = $this->fifa->calculate($homeTeam->fifa_ranking ?? 50, $awayTeam->fifa_ranking ?? 50);
        $wcData   = $this->wcHistory->calculate($homeTeam, $awayTeam);

        $lambdaHomeForm = $formHome['attack_strength'] * $formAway['defense_weakness'] * $wcAvg;
        $lambdaAwayForm = $formAway['attack_strength'] * $formHome['defense_weakness'] * $wcAvg;

        $lambdaHomeFifa = $lambdaHomeForm * $fifaData['lambda_home_factor'];
        $lambdaAwayFifa = $lambdaAwayForm * $fifaData['lambda_away_factor'];

        $lambdaHomeWc   = $wcData['attack_home'] * $wcData['defense_away'] * $wcAvg;
        $lambdaAwayWc   = $wcData['attack_away'] * $wcData['defense_home'] * $wcAvg;

        if ($hasH2h) {
            $h2hData       = $this->h2h->calculate($homeTeam->id, $awayTeam->id);
            $lambdaHomeH2h = $h2hData['attack_strength_home'] * $h2hData['defense_weakness_away'] * $wcAvg;
            $lambdaAwayH2h = $h2hData['attack_strength_away'] * $h2hData['defense_weakness_home'] * $wcAvg;

            $wHome = ['form' => 0.40, 'h2h' => 0.30, 'fifa' => 0.20, 'wc' => 0.10];
            $wAway = ['form' => 0.40, 'h2h' => 0.30, 'fifa' => 0.20, 'wc' => 0.10];

            $lambdaHome = ($lambdaHomeForm * $wHome['form'])
                + ($lambdaHomeH2h  * $wHome['h2h'])
                + ($lambdaHomeFifa * $wHome['fifa'])
                + ($lambdaHomeWc   * $wHome['wc']);

            $lambdaAway = ($lambdaAwayForm * $wAway['form'])
                + ($lambdaAwayH2h  * $wAway['h2h'])
                + ($lambdaAwayFifa * $wAway['fifa'])
                + ($lambdaAwayWc   * $wAway['wc']);
        } else {
            $h2hData       = null;
            $lambdaHomeH2h = null;
            $lambdaAwayH2h = null;

            $wHome = ['form' => 0.70, 'h2h' => 0.00, 'fifa' => 0.20, 'wc' => 0.10];
            $wAway = ['form' => 0.70, 'h2h' => 0.00, 'fifa' => 0.20, 'wc' => 0.10];

            $lambdaHome = ($lambdaHomeForm * $wHome['form'])
                + ($lambdaHomeFifa * $wHome['fifa'])
                + ($lambdaHomeWc   * $wHome['wc']);

            $lambdaAway = ($lambdaAwayForm * $wAway['form'])
                + ($lambdaAwayFifa * $wAway['fifa'])
                + ($lambdaAwayWc   * $wAway['wc']);
        }

        $breakdown = [
            'has_h2h' => $hasH2h,
            'wc_avg'  => $wcAvg,
            'home'    => [
                'form' => [
                    'attack'         => round($formHome['attack_strength'], 4),
                    'defense'        => round($formAway['defense_weakness'], 4),
                    'avg_scored'     => round($formHome['avg_scored'], 2),
                    'avg_conceded'   => round($formHome['avg_conceded'], 2),
                    'matches'        => $formHome['matches_analyzed'],
                    'lambda'         => round($lambdaHomeForm, 4),
                    'weight'         => $wHome['form'],
                    'contribution'   => round($lambdaHomeForm * $wHome['form'], 4),
                ],
                'h2h' => $hasH2h ? [
                    'attack'       => round($h2hData['attack_strength_home'], 4),
                    'defense'      => round($h2hData['defense_weakness_away'], 4),
                    'matches'      => $h2hData['matches_analyzed'],
                    'lambda'       => round($lambdaHomeH2h, 4),
                    'weight'       => $wHome['h2h'],
                    'contribution' => round($lambdaHomeH2h * $wHome['h2h'], 4),
                ] : null,
                'fifa' => [
                    'ranking'      => $homeTeam->fifa_ranking ?? 50,
                    'factor'       => round($fifaData['lambda_home_factor'], 4),
                    'lambda'       => round($lambdaHomeFifa, 4),
                    'weight'       => $wHome['fifa'],
                    'contribution' => round($lambdaHomeFifa * $wHome['fifa'], 4),
                ],
                'wc' => [
                    'avg_scored'   => round($homeTeam->avg_goals_scored_wc, 2),
                    'avg_conceded' => round($awayTeam->avg_goals_conceded_wc, 2),
                    'attack'       => round($wcData['attack_home'], 4),
                    'defense'      => round($wcData['defense_away'], 4),
                    'lambda'       => round($lambdaHomeWc, 4),
                    'weight'       => $wHome['wc'],
                    'contribution' => round($lambdaHomeWc * $wHome['wc'], 4),
                ],
                'lambda_total' => round($lambdaHome, 4),
            ],
            'away' => [
                'form' => [
                    'attack'         => round($formAway['attack_strength'], 4),
                    'defense'        => round($formHome['defense_weakness'], 4),
                    'avg_scored'     => round($formAway['avg_scored'], 2),
                    'avg_conceded'   => round($formAway['avg_conceded'], 2),
                    'matches'        => $formAway['matches_analyzed'],
                    'lambda'         => round($lambdaAwayForm, 4),
                    'weight'         => $wAway['form'],
                    'contribution'   => round($lambdaAwayForm * $wAway['form'], 4),
                ],
                'h2h' => $hasH2h ? [
                    'attack'       => round($h2hData['attack_strength_away'], 4),
                    'defense'      => round($h2hData['defense_weakness_home'], 4),
                    'matches'      => $h2hData['matches_analyzed'],
                    'lambda'       => round($lambdaAwayH2h, 4),
                    'weight'       => $wAway['h2h'],
                    'contribution' => round($lambdaAwayH2h * $wAway['h2h'], 4),
                ] : null,
                'fifa' => [
                    'ranking'      => $awayTeam->fifa_ranking ?? 50,
                    'factor'       => round($fifaData['lambda_away_factor'], 4),
                    'lambda'       => round($lambdaAwayFifa, 4),
                    'weight'       => $wAway['fifa'],
                    'contribution' => round($lambdaAwayFifa * $wAway['fifa'], 4),
                ],
                'wc' => [
                    'avg_scored'   => round($awayTeam->avg_goals_scored_wc, 2),
                    'avg_conceded' => round($homeTeam->avg_goals_conceded_wc, 2),
                    'attack'       => round($wcData['attack_away'], 4),
                    'defense'      => round($wcData['defense_home'], 4),
                    'lambda'       => round($lambdaAwayWc, 4),
                    'weight'       => $wAway['wc'],
                    'contribution' => round($lambdaAwayWc * $wAway['wc'], 4),
                ],
                'lambda_total' => round($lambdaAway, 4),
            ],
        ];

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
                'breakdown'      => $breakdown,
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
