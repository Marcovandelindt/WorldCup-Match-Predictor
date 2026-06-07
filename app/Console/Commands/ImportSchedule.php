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
                $tla  = $match[$side]['tla'] ?? '';
                $flag = $this->flagFromTla($tla);

                $team = Team::firstOrCreate(
                    ['api_id' => $match[$side]['id']],
                    [
                        'name'       => $match[$side]['name'],
                        'short_name' => $match[$side]['shortName'] ?? null,
                        'fifa_code'  => $tla ?: null,
                        'flag_emoji' => $flag,
                    ]
                );

                // Vul vlag in voor bestaande teams die nog geen emoji hebben
                if (! $team->wasRecentlyCreated && empty($team->flag_emoji) && $flag !== '') {
                    $team->update(['flag_emoji' => $flag]);
                }

                if ($team->wasRecentlyCreated) {
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

    private function flagFromTla(string $tla): string
    {
        // Subdivision flags — flag-icons gebruikt gb-eng, gb-sct, gb-wls
        $subdivisions = [
            'ENG' => 'gb-eng',
            'SCO' => 'gb-sct',
            'WAL' => 'gb-wls',
        ];

        if (isset($subdivisions[$tla])) {
            return $subdivisions[$tla];
        }

        // FIFA TLA → ISO 3166-1 alpha-2 (lowercase, voor flag-icons CSS-klasse fi-XX)
        $map = [
            'AFG' => 'af', 'ALB' => 'al', 'ALG' => 'dz', 'AND' => 'ad', 'ANG' => 'ao',
            'ANT' => 'ag', 'ARG' => 'ar', 'ARM' => 'am', 'ARU' => 'aw', 'ASA' => 'as',
            'AUS' => 'au', 'AUT' => 'at', 'AZE' => 'az', 'BAH' => 'bs', 'BAN' => 'bd',
            'BDI' => 'bi', 'BEL' => 'be', 'BEN' => 'bj', 'BER' => 'bm', 'BHU' => 'bt',
            'BIH' => 'ba', 'BLR' => 'by', 'BLZ' => 'bz', 'BOL' => 'bo', 'BOT' => 'bw',
            'BRA' => 'br', 'BRB' => 'bb', 'BRU' => 'bn', 'BUL' => 'bg', 'BUR' => 'bf',
            'CAM' => 'kh', 'CAN' => 'ca', 'CAY' => 'ky', 'CGO' => 'cg', 'CHA' => 'td',
            'CHI' => 'cl', 'CHN' => 'cn', 'CIV' => 'ci', 'CMR' => 'cm', 'COD' => 'cd',
            'COL' => 'co', 'COM' => 'km', 'CPV' => 'cv', 'CRC' => 'cr', 'CRO' => 'hr',
            'CUB' => 'cu', 'CYP' => 'cy', 'CZE' => 'cz', 'DEN' => 'dk', 'DJI' => 'dj',
            'DMA' => 'dm', 'DOM' => 'do', 'ECU' => 'ec', 'EGY' => 'eg', 'EQG' => 'gq',
            'ERI' => 'er', 'ESP' => 'es', 'EST' => 'ee', 'ETH' => 'et', 'FIJ' => 'fj',
            'FIN' => 'fi', 'FRA' => 'fr', 'FRO' => 'fo', 'GAB' => 'ga', 'GAM' => 'gm',
            'GEO' => 'ge', 'GER' => 'de', 'GHA' => 'gh', 'GIB' => 'gi', 'GNB' => 'gw',
            'GRE' => 'gr', 'GRN' => 'gd', 'GUA' => 'gt', 'GUI' => 'gn', 'GUM' => 'gu',
            'GUY' => 'gy', 'HAI' => 'ht', 'HKG' => 'hk', 'HON' => 'hn', 'HUN' => 'hu',
            'IDN' => 'id', 'IND' => 'in', 'IRL' => 'ie', 'IRN' => 'ir', 'IRQ' => 'iq',
            'ISL' => 'is', 'ISR' => 'il', 'ITA' => 'it', 'JAM' => 'jm', 'JOR' => 'jo',
            'JPN' => 'jp', 'KAZ' => 'kz', 'KEN' => 'ke', 'KGZ' => 'kg', 'KOR' => 'kr',
            'KOS' => 'xk', 'KSA' => 'sa', 'KUW' => 'kw', 'LAO' => 'la', 'LBA' => 'ly',
            'LBN' => 'lb', 'LBR' => 'lr', 'LCA' => 'lc', 'LES' => 'ls', 'LIE' => 'li',
            'LTU' => 'lt', 'LUX' => 'lu', 'LVA' => 'lv', 'MAC' => 'mo', 'MAD' => 'mg',
            'MAR' => 'ma', 'MAS' => 'my', 'MDA' => 'md', 'MDV' => 'mv', 'MEX' => 'mx',
            'MKD' => 'mk', 'MLI' => 'ml', 'MLT' => 'mt', 'MNE' => 'me', 'MON' => 'mc',
            'MOZ' => 'mz', 'MRI' => 'mu', 'MTN' => 'mr', 'MWI' => 'mw', 'MYA' => 'mm',
            'NAM' => 'na', 'NCA' => 'ni', 'NED' => 'nl', 'NEP' => 'np', 'NGA' => 'ng',
            'NOR' => 'no', 'NZL' => 'nz', 'OMA' => 'om', 'PAK' => 'pk', 'PAN' => 'pa',
            'PAR' => 'py', 'PER' => 'pe', 'PHI' => 'ph', 'PLE' => 'ps', 'PNG' => 'pg',
            'POL' => 'pl', 'POR' => 'pt', 'PRK' => 'kp', 'PUR' => 'pr', 'QAT' => 'qa',
            'ROU' => 'ro', 'RSA' => 'za', 'RUS' => 'ru', 'RWA' => 'rw', 'SAM' => 'ws',
            'SEN' => 'sn', 'SIN' => 'sg', 'SKN' => 'kn', 'SLE' => 'sl', 'SLV' => 'sv',
            'SMR' => 'sm', 'SOL' => 'sb', 'SOM' => 'so', 'SRB' => 'rs', 'SRI' => 'lk',
            'SSD' => 'ss', 'STP' => 'st', 'SUD' => 'sd', 'SUI' => 'ch', 'SUR' => 'sr',
            'SVK' => 'sk', 'SVN' => 'si', 'SWE' => 'se', 'SWZ' => 'sz', 'SYR' => 'sy',
            'TAH' => 'pf', 'TAN' => 'tz', 'TGA' => 'to', 'THA' => 'th', 'TJK' => 'tj',
            'TKM' => 'tm', 'TLS' => 'tl', 'TOG' => 'tg', 'TPE' => 'tw', 'TRI' => 'tt',
            'TUN' => 'tn', 'TUR' => 'tr', 'TUV' => 'tv', 'UAE' => 'ae', 'UGA' => 'ug',
            'UKR' => 'ua', 'URU' => 'uy', 'USA' => 'us', 'UZB' => 'uz', 'VAN' => 'vu',
            'VEN' => 've', 'VGB' => 'vg', 'VIE' => 'vn', 'VIN' => 'vc', 'YEM' => 'ye',
            'ZAM' => 'zm', 'ZIM' => 'zw',
        ];

        return $map[$tla] ?? '';
    }
}
