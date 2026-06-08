<?php

namespace App\Console\Commands;

use App\Models\EloRating;
use App\Models\Team;
use App\Services\EloCalculator;
use Illuminate\Console\Command;

class CalculateElo extends Command
{
    protected $signature = 'wk:calculate-elo
                            {--file=storage/app/results.csv : Pad naar de Kaggle results.csv}
                            {--from=2006-01-01 : Begindatum — alles voor deze datum wordt niet meegenomen}';

    protected $description = 'Bereken Elo-ratings voor alle landen op basis van de Kaggle CSV';

    // Kaggle naam => DB-teamnaam (zelfde als ImportHistoricalData)
    private array $nameMapping = [
        'USA'                    => 'United States',
        'IR Iran'                => 'Iran',
        'DR Congo'               => 'Congo DR',
        'Cape Verde'             => 'Cape Verde Islands',
        'Czech Republic'         => 'Czechia',
        'Bosnia and Herzegovina' => 'Bosnia-Herzegovina',
    ];

    public function handle(EloCalculator $calculator): int
    {
        $file = $this->option('file');

        if (! file_exists($file)) {
            $this->error("Bestand niet gevonden: {$file}");
            return self::FAILURE;
        }

        $fromDate = $this->option('from');
        $today    = date('Y-m-d');

        $this->info("Elo-ratings berekenen vanaf {$fromDate}...");

        // ── Lees CSV ──────────────────────────────────────────────────────────
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);
        $rows   = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($header)) continue;
            $r = array_combine($header, $row);

            if ($r['date'] < $fromDate)  continue;
            if ($r['date'] > $today)     continue;
            if ($r['home_score'] === '' || $r['away_score'] === '') continue;

            $rows[] = $r;
        }

        fclose($handle);

        $this->line(number_format(count($rows)) . ' wedstrijden ingelezen.');

        // ── Verwerk wedstrijden chronologisch ─────────────────────────────────
        $ratings   = [];
        $played    = [];
        $processed = 0;

        foreach ($rows as $row) {
            $home  = $row['home_team'];
            $away  = $row['away_team'];
            $homeG = (int) $row['home_score'];
            $awayG = (int) $row['away_score'];
            $k     = $calculator->kFactor($row['tournament']);

            $homeElo = $ratings[$home] ?? EloCalculator::BASE;
            $awayElo = $ratings[$away] ?? EloCalculator::BASE;

            $homeExp = $calculator->expected($homeElo, $awayElo);
            $awayExp = 1 - $homeExp;

            $homeActual = match (true) {
                $homeG > $awayG => 1.0,
                $homeG < $awayG => 0.0,
                default         => 0.5,
            };

            $ratings[$home] = $calculator->updated($homeElo, $homeExp, $homeActual, $k);
            $ratings[$away] = $calculator->updated($awayElo, $awayExp, 1 - $homeActual, $k);

            $played[$home] = ($played[$home] ?? 0) + 1;
            $played[$away] = ($played[$away] ?? 0) + 1;
            $processed++;
        }

        $this->line("{$processed} wedstrijden verwerkt · " . count($ratings) . ' teams bijgehouden.');

        // ── Opslaan ───────────────────────────────────────────────────────────
        EloRating::truncate();

        $insert = [];
        foreach ($ratings as $teamName => $rating) {
            $insert[] = [
                'team_name'      => $teamName,
                'rating'         => round($rating, 2),
                'matches_played' => $played[$teamName] ?? 0,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }

        foreach (array_chunk($insert, 500) as $chunk) {
            EloRating::insert($chunk);
        }

        // ── Top 10 overall ────────────────────────────────────────────────────
        arsort($ratings);
        $this->newLine();
        $this->info('Top 10 Elo-ratings wereldwijd:');
        foreach (array_slice($ratings, 0, 10, true) as $name => $rating) {
            $this->line(sprintf('  %-30s  %d', $name, round($rating)));
        }

        // ── WK 2026 teams ─────────────────────────────────────────────────────
        $wkTeams = Team::orderBy('name')->get();
        $missing = [];
        $wkRatings = [];

        foreach ($wkTeams as $team) {
            $kaggleName = array_search($team->name, $this->nameMapping) ?: $team->name;
            $rating     = $ratings[$kaggleName] ?? ($ratings[$team->name] ?? null);

            if ($rating === null) {
                $missing[] = $team->name;
            } else {
                $wkRatings[$team->name] = $rating;
            }
        }

        arsort($wkRatings);
        $this->newLine();
        $this->info('Elo-ratings WK 2026 teams (hoogste eerst):');
        foreach ($wkRatings as $name => $rating) {
            $this->line(sprintf('  %-30s  %d', $name, round($rating)));
        }

        if ($missing) {
            $this->newLine();
            $this->warn('Geen Elo gevonden voor (voeg toe aan $nameMapping):');
            foreach ($missing as $name) {
                $this->line("  - {$name}");
            }
        }

        $this->newLine();
        $this->info('Klaar. Draai wk:generate-predictions --force om voorspellingen bij te werken.');

        return self::SUCCESS;
    }
}
