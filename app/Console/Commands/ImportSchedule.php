<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\Team;
use App\Services\Api\FootballDataClient;
use Illuminate\Console\Command;

class ImportSchedule extends Command
{
    protected $signature   = 'wk:import-schedule';
    protected $description = 'Importeer het WK 2026 speelschema via de football-data.org API';

    public function handle(FootballDataClient $client): void
    {
        $this->info('Speelschema ophalen...');
        $data    = $client->getWCSchedule();
        $matches = $data['matches'] ?? [];

        $teamCount  = 0;
        $matchCount = 0;

        foreach ($matches as $match) {
            foreach (['homeTeam', 'awayTeam'] as $side) {
                $created = Team::firstOrCreate(
                    ['api_id' => $match[$side]['id']],
                    [
                        'name'       => $match[$side]['name'],
                        'short_name' => $match[$side]['shortName'] ?? null,
                        'fifa_code'  => $match[$side]['tla'] ?? null,
                    ]
                );
                if ($created->wasRecentlyCreated) {
                    $teamCount++;
                }
            }

            $homeTeam = Team::where('api_id', $match['homeTeam']['id'])->first();
            $awayTeam = Team::where('api_id', $match['awayTeam']['id'])->first();

            FootballMatch::updateOrCreate(
                ['api_id' => $match['id']],
                [
                    'home_team_id' => $homeTeam->id,
                    'away_team_id' => $awayTeam->id,
                    'match_date'   => $match['utcDate'],
                    'stage'        => $match['stage'],
                    'group_name'   => $match['group'] ?? null,
                    'status'       => $match['status'],
                    'venue'        => $match['venue']['name'] ?? null,
                ]
            );
            $matchCount++;
        }

        $this->info("{$teamCount} teams en {$matchCount} wedstrijden geïmporteerd.");
    }
}
