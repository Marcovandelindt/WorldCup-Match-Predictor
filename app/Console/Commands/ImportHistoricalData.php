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
                            {--from=2000-01-01 : Alleen wedstrijden na deze datum meenemen voor form-data}';

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

        $this->info('Stap 1/3 — CSV inlezen...');
        $rows = $this->readCsv($file);
        $this->line(number_format(count($rows)) . ' wedstrijden ingelezen.');

        // Resolve CSV-rijen; track alleen ongekende namen die voorkomen naast een WK-team
        $resolved         = [];
        $unknownVsWkTeam  = [];

        foreach ($rows as $row) {
            if ($row['home_score'] === '' || $row['away_score'] === '') {
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
        $this->info("Stap 2/3 — Recente wedstrijden importeren (vanaf {$fromDate})...");

        $recentByTeam = [];

        foreach ($resolved as $r) {
            if ($r['date'] < $fromDate) {
                continue;
            }

            $homeId = $r['home']->id;
            $awayId = $r['away']->id;

            if (isset($wkTeamIds[$homeId])) {
                $recentByTeam[$homeId][] = [
                    'opponent_name'  => $r['away']->name,
                    'match_date'     => $r['date'],
                    'goals_scored'   => $r['home_score'],
                    'goals_conceded' => $r['away_score'],
                    'result'         => $this->result($r['home_score'], $r['away_score']),
                    'competition'    => $r['tournament'],
                ];
            }

            if (isset($wkTeamIds[$awayId])) {
                $recentByTeam[$awayId][] = [
                    'opponent_name'  => $r['home']->name,
                    'match_date'     => $r['date'],
                    'goals_scored'   => $r['away_score'],
                    'goals_conceded' => $r['home_score'],
                    'result'         => $this->result($r['away_score'], $r['home_score']),
                    'competition'    => $r['tournament'],
                ];
            }
        }

        TeamRecentMatch::query()->delete();

        $total  = count($recentByTeam);
        $i      = 0;
        $insert = [];

        foreach ($recentByTeam as $teamId => $matches) {
            $i++;
            $team = $teams->find($teamId);

            usort($matches, fn ($a, $b) => strcmp($b['match_date'], $a['match_date']));
            $matches = array_slice($matches, 0, 30);

            $this->line(sprintf('[%d/%d] %s — %d wedstrijden', $i, $total, $team->name, count($matches)));

            foreach ($matches as $m) {
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
        $teamsWithData = array_keys($recentByTeam);
        $missingTeams  = $teams->filter(fn ($t) => ! in_array($t->id, $teamsWithData));

        if ($missingTeams->isNotEmpty()) {
            $this->newLine();
            $this->warn('Geen form-data gevonden voor de volgende WK-teams (naam-mismatch?):');
            foreach ($missingTeams as $t) {
                $this->line("  - {$t->name}");
            }
        }

        $this->newLine();
        $this->info('Stap 3/3 — H2H wedstrijden importeren...');

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
