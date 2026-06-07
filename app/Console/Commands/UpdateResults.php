<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Services\Api\FootballDataClient;
use App\Services\PredictionScorer;
use Illuminate\Console\Command;

class UpdateResults extends Command
{
    protected $signature   = 'wk:update-results';
    protected $description = 'Haal uitslagen op en scoor voorspellingen';

    public function handle(FootballDataClient $client, PredictionScorer $scorer): void
    {
        $this->info('Uitslagen ophalen...');

        $matches = FootballMatch::where('status', 'FINISHED')
            ->whereDoesntHave('accuracy')
            ->whereHas('prediction')
            ->with(['prediction'])
            ->get();

        $count = 0;

        foreach ($matches as $match) {
            $data = $client->getMatch($match->api_id);

            $match->update([
                'home_score' => $data['score']['fullTime']['home'],
                'away_score' => $data['score']['fullTime']['away'],
                'status'     => 'FINISHED',
            ]);

            $scorer->evaluate($match->prediction, $match);
            $count++;
            sleep(7);
        }

        $this->info("{$count} wedstrijden gescoord.");
    }
}
