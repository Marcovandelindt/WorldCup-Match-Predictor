<?php

namespace App\Services\Api;

use Illuminate\Support\Facades\DB;

class ApiCache
{
    public function get(string $key): ?array
    {
        $row = DB::table('api_caches')
            ->where('cache_key', $key)
            ->where('expires_at', '>', now())
            ->first();

        return $row ? json_decode($row->response_body, true) : null;
    }

    public function set(string $key, string $endpoint, array $data): void
    {
        $ttl     = (int) config('services.football_data.cache_ttl_hours', 6);
        $expires = now()->addHours($ttl);

        DB::table('api_caches')->upsert(
            [
                'cache_key'     => $key,
                'endpoint'      => $endpoint,
                'response_body' => json_encode($data),
                'expires_at'    => $expires,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
            ['cache_key'],
            ['response_body', 'expires_at', 'updated_at']
        );
    }
}
