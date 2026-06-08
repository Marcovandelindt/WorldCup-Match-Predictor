<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\TeamH2hMatch;
use App\Models\TeamRecentMatch;
use Illuminate\Console\Command;

class ImportHistoricalData extends Command
{
    protected $signature = 'wk:import-historical-data
                            {--file=storage/app/results.csv : Pad naar de Kaggle results.csv}
                            {--from=2000-01-01 : Alleen wedstrijden na deze datum meenemen voor form-data}
                            {--wc-from=1994-01-01 : Alleen WK-wedstrijden na deze datum meenemen voor WK-historie}';

    protected $description = 'Importeer historische interland data uit de Kaggle CSV in de database';

    // Kaggle naam => naam in onze teams tabel
    private array $nameMapping = [
        'USA'                    => 'United States',
        'IR Iran'                => 'Iran',
        'DR Congo'               => 'Congo DR',
        'Cape Verde'             => 'Cape Verde Islands',
        'Czech Republic'         => 'Czechia',
        'Bosnia and Herzegovina' => 'Bosnia-Herzegovina',
    ];

    public function handle(): void
    {
        $file = $this->option('file');

        if (! file_exists($file)) {
            $this->error("Bestand niet gevonden: {$file}");
            $this->line('Download results.csv van: https://www.kaggle.com/datasets/martj42/international-football-results-from-1872-to-2017');
            $this->line('Zet het bestand in storage/app/results.csv of geef het pad mee via --file=');
            return;
        }

        $fromDate = $this->option('from');

        // Bouw naam-lookup: lowercase naam => Team model
        $teams  = Team::all();
        $lookup = [];

        foreach ($teams as $team) {
            $lookup[strtolower($team->name)] = $team;
            if ($team->short_name) {
                $lookup[strtolower($team->short_name)] = $team;
            }
        }

        $wkTeamIds = $teams->pluck('id')->flip()->toArray();

        $this->info('Stap 1/4 — CSV inlezen...');
        $rows = $this->readCsv($file);
        $this->line(number_format(count($rows)) . ' wedstrijden ingelezen.');

        // Resolve CSV-rijen; track alleen ongekende namen die voorkomen naast een WK-team
        $resolved         = [];
        $unknownVsWkTeam  = [];

        $today = date('Y-m-d');

        foreach ($rows as $row) {
            if ($row['home_score'] === '' || $row['away_score'] === '') {
                continue;
            }

            if ($row['date'] > $today) {
                continue;
            }

            $home = $this->resolveTeam($row['home_team'], $lookup);
            $away = $this->resolveTeam($row['away_team'], $lookup);

            if ($home && $away) {
                $resolved[] = [
                    'date'       => $row['date'],
                    'home'       => $home,
                    'away'       => $away,
                    'home_score' => (int) $row['home_score'],
                    'away_score' => (int) $row['away_score'],
                    'tournament' => $row['tournament'],
                ];
                continue;
            }

            // Rapporteer alleen als de andere kant wél een WK-team is
            if ($home && isset($wkTeamIds[$home->id]) && ! $away) {
                $unknownVsWkTeam[$row['away_team']] = true;
            }
            if ($away && isset($wkTeamIds[$away->id]) && ! $home) {
                $unknownVsWkTeam[$row['home_team']] = true;
            }
        }

        $this->newLine();
        $this->info("Stap 2/4 — Recente wedstrijden importeren (vanaf {$fromDate})...");

        // Verzamel form-wedstrijden (alle competities, vanaf --from)
        // en WK-wedstrijden (alle edities, vanaf --wc-from) apart
        $formByTeam = [];
        $wcHistByTeam = [];
        $wcFrom = $this->option('wc-from');

        foreach ($resolved as $r) {
            $homeId = $r['home']->id;
            $awayId = $r['away']->id;
            $isWc   = $r['tournament'] === 'FIFA World Cup';

            foreach ([
                $homeId => ['scored' => $r['home_score'], 'conceded' => $r['away_score'], 'opponent' => $r['away']->name],
                $awayId => ['scored' => $r['away_score'], 'conceded' => $r['home_score'], 'opponent' => $r['home']->name],
            ] as $teamId => $side) {
                if (! isset($wkTeamIds[$teamId])) continue;

                $entry = [
                    'opponent_name'  => $side['opponent'],
                    'match_date'     => $r['date'],
                    'goals_scored'   => $side['scored'],
                    'goals_conceded' => $side['conceded'],
                    'result'         => $this->result($side['scored'], $side['conceded']),
                    'competition'    => $r['tournament'],
                ];

                if ($r['date'] >= $fromDate) {
                    $formByTeam[$teamId][] = $entry;
                }

                if ($isWc && $r['date'] >= $wcFrom) {
                    $wcHistByTeam[$teamId][] = $entry;
                }
            }
        }

        TeamRecentMatch::query()->delete();

        $allTeamIds = array_unique(array_merge(array_keys($formByTeam), array_keys($wcHistByTeam)));
        $total      = count($allTeamIds);
        $i          = 0;
        $insert     = [];

        foreach ($allTeamIds as $teamId) {
            $i++;
            $team = $teams->find($teamId);

            // Laatste 30 form-wedstrijden
            $form = $formByTeam[$teamId] ?? [];
            usort($form, fn ($a, $b) => strcmp($b['match_date'], $a['match_date']));
            $form = array_slice($form, 0, 30);

            // Alle WK-wedstrijden; verwijder duplicaten die al in form zitten
            $formDates   = array_column($form, 'match_date');
            $wcUnique    = array_filter(
                $wcHistByTeam[$teamId] ?? [],
                fn ($m) => ! in_array($m['match_date'], $formDates)
            );

            $combined = array_merge($form, array_values($wcUnique));

            $this->line(sprintf(
                '[%d/%d] %s — %d form + %d WK-historic',
                $i, $total, $team->name, count($form), count($wcUnique)
            ));

            foreach ($combined as $m) {
                $insert[] = [
                    'team_id'         => $teamId,
                    'opponent_api_id' => null,
                    'opponent_name'   => $m['opponent_name'],
                    'match_date'      => $m['match_date'],
                    'goals_scored'    => $m['goals_scored'],
                    'goals_conceded'  => $m['goals_conceded'],
                    'result'          => $m['result'],
                    'competition'     => $m['competition'],
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];
            }
        }

        foreach (array_chunk($insert, 500) as $chunk) {
            TeamRecentMatch::insert($chunk);
        }

        // Rapporteer WK-teams zonder data
        $teamsWithData = $allTeamIds;
        $missingTeams  = $teams->filter(fn ($t) => ! in_array($t->id, $teamsWithData));

        if ($missingTeams->isNotEmpty()) {
            $this->newLine();
            $this->warn('Geen form-data gevonden voor de volgende WK-teams (naam-mismatch?):');
            foreach ($missingTeams as $t) {
                $this->line("  - {$t->name}");
            }
        }

        $this->newLine();
        $this->info('Stap 3/4 — H2H wedstrijden importeren...');

        TeamH2hMatch::query()->delete();

        $h2hInsert = [];

        foreach ($resolved as $r) {
            $homeId = $r['home']->id;
            $awayId = $r['away']->id;

            if (! isset($wkTeamIds[$homeId]) || ! isset($wkTeamIds[$awayId])) {
                continue;
            }

            $h2hInsert[] = [
                'home_team_id' => $homeId,
                'away_team_id' => $awayId,
                'match_date'   => $r['date'],
                'home_score'   => $r['home_score'],
                'away_score'   => $r['away_score'],
                'competition'  => $r['tournament'],
                'created_at'   => now(),
                'updated_at'   => now(),
            ];
        }

        foreach (array_chunk($h2hInsert, 500) as $chunk) {
            TeamH2hMatch::insert($chunk);
        }

        $this->line(number_format(count($h2hInsert)) . ' H2H wedstrijden opgeslagen.');

        $this->newLine();
        $this->info("Stap 4/4 — WK-geschiedenis berekenen (vanaf {$this->option('wc-from')})...");

        $wcByTeam = [];

        foreach ($resolved as $r) {
            if ($r['tournament'] !== 'FIFA World Cup') continue;
            if ($r['date'] < $wcFrom) continue;

            $homeId = $r['home']->id;
            $awayId = $r['away']->id;

            if (isset($wkTeamIds[$homeId])) {
                $wcByTeam[$homeId]['scored'][]   = $r['home_score'];
                $wcByTeam[$homeId]['conceded'][] = $r['away_score'];
            }
            if (isset($wkTeamIds[$awayId])) {
                $wcByTeam[$awayId]['scored'][]   = $r['away_score'];
                $wcByTeam[$awayId]['conceded'][] = $r['home_score'];
            }
        }

        foreach ($teams as $team) {
            $data = $wcByTeam[$team->id] ?? null;

            $avgScored   = $data ? array_sum($data['scored'])   / count($data['scored'])   : 0;
            $avgConceded = $data ? array_sum($data['conceded']) / count($data['conceded']) : 0;
            $matches     = $data ? count($data['scored']) : 0;

            $team->update([
                'avg_goals_scored_wc'   => round($avgScored, 2),
                'avg_goals_conceded_wc' => round($avgConceded, 2),
            ]);

            $this->line(sprintf(
                '  %s — %d WK-wedstrijden · %.2f gescoord · %.2f gecasseerd',
                $team->name, $matches, $avgScored, $avgConceded
            ));
        }

        if ($unknownVsWkTeam) {
            $this->newLine();
            $this->warn('Tegenstanders van WK-teams die niet gematched konden worden (voeg toe aan $nameMapping indien nodig):');
            foreach (array_keys($unknownVsWkTeam) as $name) {
                $this->line("  '{$name}' => '???',");
            }
        }

        $this->newLine();
        $this->info('Klaar. Historische data staat in de database — voorspellingen draaien nu volledig offline.');
    }

    private function readCsv(string $file): array
    {
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);
        $rows   = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($header)) {
                $rows[] = array_combine($header, $row);
            }
        }

        fclose($handle);

        return $rows;
    }

    private function resolveTeam(string $name, array $lookup): ?Team
    {
        $mapped = $this->nameMapping[$name] ?? $name;

        return $lookup[strtolower($mapped)] ?? null;
    }

    private function result(int $scored, int $conceded): string
    {
        return match (true) {
            $scored > $conceded => 'WIN',
            $scored < $conceded => 'LOSS',
            default             => 'DRAW',
        };
    }
}
