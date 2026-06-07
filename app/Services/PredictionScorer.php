<?php

namespace App\Services;

use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Models\PredictionAccuracy;

class PredictionScorer
{
    public function evaluate(Prediction $prediction, FootballMatch $match): PredictionAccuracy
    {
        $predHome   = $prediction->predicted_home;
        $predAway   = $prediction->predicted_away;
        $actualHome = $match->home_score;
        $actualAway = $match->away_score;

        $exactScore      = $predHome === $actualHome && $predAway === $actualAway;
        $correctWinner   = $this->getResult($predHome, $predAway) === $this->getResult($actualHome, $actualAway);
        $correctGoalDiff = ($predHome - $predAway) === ($actualHome - $actualAway);

        $points = match(true) {
            $exactScore                        => 3,
            $correctWinner && $correctGoalDiff => 2,
            $correctWinner                     => 1,
            default                            => 0,
        };

        return PredictionAccuracy::updateOrCreate(
            ['prediction_id' => $prediction->id],
            [
                'match_id'          => $match->id,
                'predicted_home'    => $predHome,
                'predicted_away'    => $predAway,
                'actual_home'       => $actualHome,
                'actual_away'       => $actualAway,
                'exact_score'       => $exactScore,
                'correct_winner'    => $correctWinner,
                'correct_goal_diff' => $correctGoalDiff,
                'points_earned'     => $points,
                'evaluated_at'      => now(),
            ]
        );
    }

    private function getResult(int $home, int $away): string
    {
        return match(true) {
            $home > $away => 'HOME',
            $away > $home => 'AWAY',
            default       => 'DRAW',
        };
    }
}
