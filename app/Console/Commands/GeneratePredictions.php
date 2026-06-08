<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Services\Predictor;
use Illuminate\Console\Command;

class GeneratePredictions extends Command
{
    protected $signature = 'wk:generate-predictions
                            {--all : Genereer voor alle wedstrijden, ook zonder bestaande voorspelling}
                            {--force : Herbereken ook wedstrijden die al een breakdown hebben}';

    protected $description = 'Genereer (of herbereken) voorspellingen voor WK 2026 wedstrijden';

    public function handle(Predictor $predictor): int
    {
        $query = FootballMatch::with(['homeTeam', 'awayTeam', 'prediction']);

        if (! $this->option('all')) {
            $query->whereHas('prediction');
        }

        if (! $this->option('force') && ! $this->option('all')) {
            $query->whereHas('prediction', fn ($q) => $q->whereNull('breakdown'));
        }

        $matches = $query->get();

        if ($matches->isEmpty()) {
            $this->info('Geen wedstrijden gevonden om te (her)berekenen.');
            $this->line('  Tip: gebruik --all om alle wedstrijden te berekenen, of --force om alles te herberekenen.');
            return self::SUCCESS;
        }

        $this->info("Voorspellingen genereren voor {$matches->count()} wedstrijd(en)...");
        $this->newLine();

        $ok = 0;
        $skipped = 0;

        foreach ($matches as $match) {
            $label = "{$match->homeTeam->name} vs {$match->awayTeam->name}";

            try {
                $predictor->predict($match);
                $this->line("  <fg=green>✓</> {$label}");
                $ok++;
            } catch (\RuntimeException $e) {
                $this->line("  <fg=yellow>–</> {$label}: {$e->getMessage()}");
                $skipped++;
            }
        }

        $this->newLine();
        $this->info("Klaar. {$ok} berekend, {$skipped} overgeslagen.");

        return self::SUCCESS;
    }
}
