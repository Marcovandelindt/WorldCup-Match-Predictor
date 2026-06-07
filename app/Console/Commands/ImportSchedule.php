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
            // Sla wedstrijden over waarbij een van de teams nog niet bekend is (bijv. knockout fase TBD)
            if (empty($match['homeTeam']['id']) || empty($match['awayTeam']['id'])) {
                continue;
            }

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
                    'stage'        => $this->mapStage($match['stage']),
                    'group_name'   => $match['group'] ?? null,
                    'status'       => $this->mapStatus($match['status']),
                    'venue'        => $match['venue']['name'] ?? null,
                ]
            );
            $matchCount++;
        }

        $this->info("{$teamCount} teams en {$matchCount} wedstrijden geïmporteerd.");
    }

    private function mapStage(string $apiStage): string
    {
        return match($apiStage) {
            'GROUP_STAGE'           => 'GROUP',
            'ROUND_OF_16'           => 'R16',
            'QUARTER_FINAL'         => 'QF',
            'SEMI_FINAL'            => 'SF',
            'THIRD_PLACE_PLAY_OFF'  => 'THIRD',
            default                 => 'FINAL',
        };
    }

    private function mapStatus(string $apiStatus): string
    {
        return match($apiStatus) {
            'IN_PLAY', 'PAUSED'                 => 'LIVE',
            'FINISHED'                          => 'FINISHED',
            'POSTPONED', 'CANCELLED', 'SUSPENDED' => 'POSTPONED',
            default                             => 'SCHEDULED',
        };
    }
}
