<?php

namespace App\Services;

class EloCalculator
{
    const BASE = 1500.0;

    public function expected(float $ratingA, float $ratingB): float
    {
        return 1 / (1 + pow(10, ($ratingB - $ratingA) / 400));
    }

    public function updated(float $rating, float $expected, float $actual, int $k): float
    {
        return $rating + $k * ($actual - $expected);
    }

    public function kFactor(string $competition): int
    {
        $lower = strtolower($competition);

        if (str_contains($lower, 'fifa world cup') && ! str_contains($lower, 'qualif')) {
            return 60;
        }

        if (
            str_contains($lower, 'uefa european') ||
            str_contains($lower, 'copa america') ||
            str_contains($lower, 'africa cup') ||
            str_contains($lower, 'afc asian cup') ||
            str_contains($lower, 'gold cup') ||
            str_contains($lower, 'concacaf nations')
        ) {
            return 50;
        }

        if (str_contains($lower, 'qualif') || str_contains($lower, 'qualification')) {
            return 40;
        }

        return 20;
    }
}
