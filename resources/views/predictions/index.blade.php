@extends('layouts.app')

@section('title', 'GOALCAST — Voorspellingen · WK 2026')

@section('content')
<main class="page">
    <div class="page-head">
        <div>
            <p class="eyebrow">Alle gegenereerde voorspellingen</p>
            <h1 class="page-title">Voorspellingen</h1>
        </div>
        <div class="legend">
            <span class="legend-item"><span class="status-dot exact"></span> Exacte score</span>
            <span class="legend-item"><span class="status-dot winner"></span> Winnaar correct</span>
            <span class="legend-item"><span class="status-dot wrong"></span> Fout</span>
        </div>
    </div>

    {{-- Stats --}}
    <div class="pred-stats">
        <div class="pred-stat">
            <span class="v mono">{{ $stats['totalPredicted'] }}</span>
            <span class="l">Voorspeld</span>
        </div>
        <div class="pred-stat">
            <span class="v mono">{{ $stats['totalPlayed'] }}</span>
            <span class="l">Gespeeld</span>
        </div>
        <div class="pred-stat">
            <span class="v mono green">
                {{ $stats['totalPlayed'] > 0 ? number_format($stats['exactCount'] / $stats['totalPlayed'] * 100, 1, ',', '') : '–' }}%
            </span>
            <span class="l">Exact correct</span>
        </div>
        <div class="pred-stat">
            <span class="v mono">
                {{ $stats['totalPlayed'] > 0 ? number_format($stats['winnerCount'] / $stats['totalPlayed'] * 100, 1, ',', '') : '–' }}%
            </span>
            <span class="l">Winnaar correct</span>
        </div>
        <div class="pred-stat">
            <span class="v mono green">{{ $stats['totalPoints'] }}</span>
            <span class="l">Totaal punten</span>
        </div>
    </div>

    @foreach($sections as $sectionKey => $section)
    @php
        $matches     = $section['matches'];
        $label       = $section['label'];
        $finished    = $matches->where('status', 'FINISHED');
        $exactCount  = $finished->filter(fn($m) => $m->accuracy?->exact_score)->count();
        $winnerCount = $finished->filter(fn($m) => $m->accuracy?->correct_winner)->count();
        $sectionPts  = $finished->sum(fn($m) => $m->accuracy?->points_earned ?? 0);
        $isFinal     = $sectionKey === 'FINAL';
    @endphp
    <section class="phase-group">
        <div class="phase-head">
            <h3>{{ $label }}</h3>
            <span class="phase-line"></span>
            @if($finished->isNotEmpty())
            <span class="phase-stat">
                <span>Exact <b>{{ $exactCount }}/{{ $finished->count() }}</b></span>
                <span>Winnaar <b>{{ $finished->count() > 0 ? round($winnerCount / $finished->count() * 100) : 0 }}%</b></span>
                <span>Punten <b>{{ $sectionPts }}</b></span>
            </span>
            @else
            <span class="phase-stat"><span class="dim">Nog niet gespeeld</span></span>
            @endif
        </div>
        <div class="card">
            <div class="fixtures">
                @foreach($matches as $match)
                @php
                    $acc = $match->accuracy;
                    $fixtureClass = 'r-todo';
                    if ($acc) {
                        $fixtureClass = $acc->exact_score ? 'r-exact' : ($acc->correct_winner ? 'r-winner' : 'r-wrong');
                    }
                    $ptsLabel = $acc ? '+' . $acc->points_earned : '–';
                    $ptsClass = $acc ? ($acc->points_earned >= 3 ? 'pos' : ($acc->points_earned > 0 ? 'mid' : 'zero')) : 'zero';
                @endphp
                <div class="fixture {{ $fixtureClass }}" data-href="{{ route('predict.show', $match) }}">
                    <span class="fx-date">{{ $match->match_date->format('j M') }}</span>
                    <span class="match-grid">
                        <span class="team home">
                            <a class="team-name" href="{{ route('teams.show', $match->homeTeam) }}">{{ $match->homeTeam->name }}</a>
                            <span class="flag fi fi-{{ $match->homeTeam->flag_emoji ?? 'xx' }}"></span>
                        </span>
                        <span class="fx-vs">vs</span>
                        <span class="team">
                            <span class="flag fi fi-{{ $match->awayTeam->flag_emoji ?? 'xx' }}"></span>
                            <a class="team-name" href="{{ route('teams.show', $match->awayTeam) }}">{{ $match->awayTeam->name }}</a>
                        </span>
                    </span>
                    <span class="fx-compare">
                        <span class="score pred">{{ $match->prediction->predicted_home }}–{{ $match->prediction->predicted_away }}</span>
                        @if($match->status === 'FINISHED')
                            <span class="arrow">→</span>
                            <span class="score real {{ $acc?->exact_score ? 'win' : '' }}">{{ $match->home_score }}–{{ $match->away_score }}</span>
                        @else
                            <span class="fx-todo-tag">nog te spelen</span>
                        @endif
                    </span>
                    <span class="badge {{ $isFinal ? 'green' : '' }}">
                        {{ $match->group_name ? str_replace('GROUP_', 'Gr. ', $match->group_name) : $label }}
                    </span>
                    <span class="t-right"><span class="pts {{ $ptsClass }}">{{ $ptsLabel }}</span></span>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endforeach
</main>
@endsection
