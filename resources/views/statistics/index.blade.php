@extends('layouts.app')

@section('title', 'GOALCAST — Statistieken · WK 2026')

@section('content')
<main class="page">
    <div class="page-head">
        <div>
            <p class="eyebrow">Prestatie-analyse</p>
            <h1 class="page-title">Statistieken</h1>
        </div>
        <div class="page-head-meta">
            <span class="live-dot"></span>
            {{ $totalPlayed }} wedstrijden · {{ $matchdayCount }} speeldagen
        </div>
    </div>

    {{-- KPI row --}}
    <section class="kpi-row">
        <div class="kpi">
            <div class="v mono" style="color:var(--green-bright)">{{ $bestMatchday['points'] ?? 0 }}</div>
            <div class="l">Beste speeldag ({{ $bestMatchday['label'] ?? '–' }})</div>
        </div>
        <div class="kpi">
            <div class="v mono">{{ $totalPlayed > 0 ? number_format($totalPoints / $totalPlayed, 1, ',', '') : '0' }}</div>
            <div class="l">Gem. punten / wedstrijd</div>
        </div>
        <div class="kpi">
            <div class="v mono">{{ $longestExactStreak }}</div>
            <div class="l">Langste reeks exact</div>
        </div>
        <div class="kpi">
            <div class="v mono" style="color:var(--green-bright)">{{ $recentTrend }}</div>
            <div class="l">Trend laatste 4 speeldagen</div>
        </div>
    </section>

    {{-- Charts grid --}}
    <section class="chart-grid section">
        {{-- Points per matchday --}}
        <div class="card chart-card">
            <div class="chart-head">
                <div>
                    <div class="chart-title">Punten per speeldag</div>
                    <div class="chart-desc">Behaalde punten per matchday · cumulatief verloop</div>
                </div>
                <div class="chart-legend">
                    <span class="li"><span class="sw" style="background:var(--green)"></span> Per speeldag</span>
                    <span class="li"><span class="sw dash"></span> Cumulatief</span>
                </div>
            </div>
            <div class="chart-box">
                <svg id="chartPoints" class="chart-svg" viewBox="0 0 640 260" preserveAspectRatio="xMidYMid meet"></svg>
            </div>
        </div>

        {{-- Accuracy by phase --}}
        <div class="card chart-card">
            <div class="chart-head">
                <div>
                    <div class="chart-title">Nauwkeurigheid per fase</div>
                    <div class="chart-desc">Aandeel exacte scores vs. correcte winnaar</div>
                </div>
                <div class="chart-legend">
                    <span class="li"><span class="sw" style="background:var(--green-bright)"></span> Exact</span>
                    <span class="li"><span class="sw" style="background:var(--orange)"></span> Winnaar</span>
                </div>
            </div>
            <div class="chart-box">
                <div class="vbars">
                    @foreach($accuracyByStage as $phase)
                    @php $isPending = $phase['total'] === 0; @endphp
                    <div class="vgroup {{ $isPending ? 'pending' : '' }}">
                        @if($isPending)
                            <div class="vbar-pair"><div class="vbar locked"></div></div>
                            <span class="lockmark">—</span>
                        @else
                        @php
                            $exactH  = round($phase['exact_pct'] * 1.7);
                            $winnerH = round($phase['winner_pct'] * 1.7);
                        @endphp
                        <div class="vbar-pair">
                            <div class="vbar exact" style="height:{{ $exactH }}px">
                                <span class="vval">{{ $phase['exact_pct'] }}%</span>
                            </div>
                            <div class="vbar winner" style="height:{{ $winnerH }}px">
                                <span class="vval">{{ $phase['winner_pct'] }}%</span>
                            </div>
                        </div>
                        @endif
                        <span class="vlabel">{{ $phase['label'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- Scoreline comparison --}}
    <section class="section">
        <div class="section-head">
            <h2 class="section-title">Meest voorspeld vs. meest werkelijk</h2>
            <span class="section-sub">Verdeling van scorelijnen over {{ $totalPlayed }} wedstrijden</span>
        </div>
        <div class="card card-pad">
            <div class="compare">
                <div class="compare-col left">
                    <h4>🎯 Meest voorspelde scorelijnen</h4>
                    @foreach($topPredicted as $s)
                    @php $w = isset($topPredicted[0]['count']) && $topPredicted[0]['count'] > 0 ? round($s['count'] / $topPredicted[0]['count'] * 100) : 0; @endphp
                    <div class="crow left">
                        <span class="cbar" data-w="{{ $w }}" style="width:0"></span>
                        <span class="cscore">{{ $s['score'] }}</span>
                        <span class="cpct">{{ $s['pct'] }}%</span>
                    </div>
                    @endforeach
                </div>
                <div class="compare-col right">
                    <h4>⚽ Meest werkelijke scorelijnen</h4>
                    @foreach($topActual as $s)
                    @php $w = isset($topActual[0]['count']) && $topActual[0]['count'] > 0 ? round($s['count'] / $topActual[0]['count'] * 100) : 0; @endphp
                    <div class="crow right">
                        <span class="cpct">{{ $s['pct'] }}%</span>
                        <span class="cscore">{{ $s['score'] }}</span>
                        <span class="cbar" data-w="{{ $w }}" style="width:0"></span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
</main>
@endsection

@push('scripts')
<script>
    window.statsChartData = @json($pointsPerMatchday);
</script>
@endpush
