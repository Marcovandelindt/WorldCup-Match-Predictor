<?php

namespace App\Services\Models;

class DixonColesModel
{
    public function __construct(private PoissonModel $poisson) {}

    public function correct(float $prob, int $home, int $away, float $lh, float $la): float
    {
        $rho = (float) config('services.football_data.dixon_coles_rho', 0.13);

        return match(true) {
            $home === 0 && $away === 0 => $prob * (1 - $lh * $la * $rho),
            $home === 1 && $away === 0 => $prob * (1 + $la * $rho),
            $home === 0 && $away === 1 => $prob * (1 + $lh * $rho),
            $home === 1 && $away === 1 => $prob * (1 - $rho),
            default                    => $prob,
        };
    }

    public function predict(float $lambdaHome, float $lambdaAway): array
    {
        $matrix     = $this->poisson->scorelines($lambdaHome, $lambdaAway);
        $scorelines = [];

        foreach ($matrix as $h => $awayGoals) {
            foreach ($awayGoals as $a => $prob) {
                $scorelines[] = [
                    'home'        => $h,
                    'away'        => $a,
                    'probability' => round(
                        $this->correct($prob, $h, $a, $lambdaHome, $lambdaAway) * 100,
                        2
                    ),
                ];
            }
        }

        usort($scorelines, fn ($x, $y) => $y['probability'] <=> $x['probability']);

        return array_slice($scorelines, 0, 10);
    }
}
