<?php

namespace App\Services\Models;

class PoissonModel
{
    public function probability(float $lambda, int $k): float
    {
        return (pow($lambda, $k) * exp(-$lambda)) / $this->factorial($k);
    }

    public function scorelines(float $lambdaHome, float $lambdaAway, int $maxGoals = 5): array
    {
        $matrix = [];

        for ($h = 0; $h <= $maxGoals; $h++) {
            for ($a = 0; $a <= $maxGoals; $a++) {
                $matrix[$h][$a] = $this->probability($lambdaHome, $h)
                    * $this->probability($lambdaAway, $a);
            }
        }

        return $matrix;
    }

    private function factorial(int $n): float
    {
        return $n <= 1 ? 1.0 : (float) $n * $this->factorial($n - 1);
    }
}
