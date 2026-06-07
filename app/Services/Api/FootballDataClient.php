<?php

namespace App\Services\Api;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class FootballDataClient
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct(private ApiCache $cache)
    {
        $this->baseUrl = config('services.football_data.base_url');
        $this->apiKey  = config('services.football_data.api_key');
    }

    private function fetch(string $endpoint): array
    {
        $cacheKey = md5($endpoint);
        $cached   = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $response = Http::withHeaders([
            'X-Auth-Token' => $this->apiKey,
        ])->get($this->baseUrl . $endpoint);

        if (! $response->successful()) {
            throw new RuntimeException("API fout {$response->status()} voor {$endpoint}");
        }

        $data = $response->json();
        $this->cache->set($cacheKey, $endpoint, $data);

        return $data;
    }

    public function getTeamMatches(int $teamApiId, int $limit = 10): array
    {
        return $this->fetch("/teams/{$teamApiId}/matches?limit={$limit}&status=FINISHED");
    }

    public function getHeadToHead(int $matchApiId): array
    {
        return $this->fetch("/matches/{$matchApiId}/head2head?limit=10");
    }

    public function getWCSchedule(): array
    {
        return $this->fetch('/competitions/WC/matches');
    }

    public function getMatch(int $matchApiId): array
    {
        return $this->fetch("/matches/{$matchApiId}");
    }
}
