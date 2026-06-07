<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\Team;
use App\Models\TeamH2hMatch;
use App\Models\TeamRecentMatch;
use App\Services\Api\FootballDataClient;
use Illuminate\Console\Command;

class ImportTeamData extends Command
{
    protected $signature   = 'wk:import-team-data';
    protected $description = 'Haal recente wedstrijden en H2H data op voor alle WK teams en sla op in de database';

    public function handle(FootballDataClient $client): void
    {
        $teams   = Team::all();
        $matches = FootballMatch::with(['homeTeam', 'awayTeam'])->get();

        $this->info("Recente wedstrijden ophalen voor {$teams->count()} teams...");
        $bar = $this->output->createProgressBar($teams->count());
        $bar->start();

        foreach ($teams as $team) {
            $this->importRecentMatches($team, $client);
            $bar->advance();

            sleep(7);
        }

        $bar->finish();
        $this->newLine();

        $this->info("H2H data ophalen voor {$matches->count()} wedstrijden...");
        $bar = $this->output->createProgressBar($matches->count());
        $bar->start();

        foreach ($matches as $match) {
            $this->importH2h($match, $client);
            $bar->advance();

            sleep(7);
        }

        $bar->finish();
        $this->newLine();
        $this->info('Klaar. Alle data staat in de database — voorspellingen draaien nu volledig offline.');
    }

    private function importRecentMatches(Team $team, FootballDataClient $client): void
    {
        $data    = $client->getTeamMatches($team->api_id, 10);
        $matches = $data['matches'] ?? [];

        TeamRecentMatch::where('team_id', $team->id)->delete();

        foreach ($matches as $match) {
            $isHome        = $match['homeTeam']['id'] === $team->api_id;
            $goalsScored   = $isHome ? $match['score']['fullTime']['home'] : $match['score']['fullTime']['away'];
            $goalsConceded = $isHome ? $match['score']['fullTime']['away'] : $match['score']['fullTime']['home'];

            $result = match(true) {
                $goalsScored > $goalsConceded => 'WIN',
                $goalsScored < $goalsConceded => 'LOSS',
                default                       => 'DRAW',
            };

            $opponentSide = $isHome ? 'awayTeam' : 'homeTeam';

            TeamRecentMatch::create([
                'team_id'         => $team->id,
                'opponent_api_id' => $match[$opponentSide]['id'],
                'opponent_name'   => $match[$opponentSide]['name'],
                'match_date'      => substr($match['utcDate'], 0, 10),
                'goals_scored'    => $goalsScored,
                'goals_conceded'  => $goalsConceded,
                'result'          => $result,
                'competition'     => $match['competition']['name'] ?? null,
            ]);
        }
    }

    private function importH2h(FootballMatch $match, FootballDataClient $client): void
    {
        $data    = $client->getHeadToHead($match->api_id);
        $matches = $data['matches'] ?? [];

        TeamH2hMatch::where('home_team_id', $match->home_team_id)
            ->where('away_team_id', $match->away_team_id)
            ->delete();

        foreach ($matches as $h2h) {
            $homeApiId = $h2h['homeTeam']['id'];
            $awayApiId = $h2h['awayTeam']['id'];

            $homeTeam = Team::where('api_id', $homeApiId)->first();
            $awayTeam = Team::where('api_id', $awayApiId)->first();

            if (! $homeTeam || ! $awayTeam) {
                continue;
            }

            TeamH2hMatch::create([
                'home_team_id' => $homeTeam->id,
                'away_team_id' => $awayTeam->id,
                'match_date'   => substr($h2h['utcDate'], 0, 10),
                'home_score'   => $h2h['score']['fullTime']['home'],
                'away_score'   => $h2h['score']['fullTime']['away'],
                'competition'  => $h2h['competition']['name'] ?? null,
            ]);
        }
    }
}
