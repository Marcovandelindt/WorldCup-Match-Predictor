@extends('layouts.app')

@section('title', 'GOALCAST — Resultaten · WK 2026')

@section('content')
<main class="page">
    <div class="page-head">
        <div>
            <p class="eyebrow">Volledig overzicht per fase</p>
            <h1 class="page-title">Resultaten</h1>
        </div>
        <div class="legend">
            <span class="legend-item"><span class="status-dot exact"></span> Exacte score</span>
            <span class="legend-item"><span class="status-dot winner"></span> Winnaar correct</span>
            <span class="legend-item"><span class="status-dot wrong"></span> Fout</span>
        </div>
    </div>

    @php
        $stageLabels = [
            'GROUP' => 'Groepsfase',
            'R16'   => 'Achtste finales',
            'QF'    => 'Kwartfinale',
            'SF'    => 'Halve finale',
            'THIRD' => 'Derde plaats',
            'FINAL' => 'Finale',
        ];
    @endphp

    @foreach($stageLabels as $stageKey => $stageLabel)
    @php
        $matches     = $matchesByStage[$stageKey] ?? collect();
        $finished    = $matches->where('status', 'FINISHED');
        $exactCount  = $finished->filter(fn($m) => $m->accuracy?->exact_score)->count();
        $winnerCount = $finished->filter(fn($m) => $m->accuracy?->correct_winner)->count();
        $stagePoints = $finished->sum(fn($m) => $m->accuracy?->points_earned ?? 0);
        $hasData     = $finished->isNotEmpty();
    @endphp
    <section class="phase-group">
        <div class="phase-head">
            <h3>{{ $stageLabel }}</h3>
            <span class="phase-line"></span>
            @if($hasData)
            <span class="phase-stat">
                <span>Exact <b>{{ $exactCount }}/{{ $finished->count() }}</b></span>
                <span>Winnaar <b>{{ $finished->count() > 0 ? round($winnerCount / $finished->count() * 100) : 0 }}%</b></span>
                <span>Punten <b>{{ $stagePoints }}</b></span>
            </span>
            @else
            <span class="phase-stat"><span class="dim">Nog niet gespeeld</span></span>
            @endif
        </div>
        <div class="card">
            <div class="fixtures">
                @forelse($matches as $match)
                @php
                    $acc = $match->accuracy;
                    $fixtureClass = 'r-todo';
                    if ($acc) {
                        $fixtureClass = $acc->exact_score ? 'r-exact' : ($acc->correct_winner ? 'r-winner' : 'r-wrong');
                    }
                    $ptsClass = 'zero';
                    $ptsLabel = '–';
                    if ($acc) {
                        $ptsLabel = '+' . $acc->points_earned;
                        $ptsClass = $acc->points_earned >= 3 ? 'pos' : ($acc->points_earned > 0 ? 'mid' : 'zero');
                    }
                @endphp
                <div class="fixture {{ $fixtureClass }}"
                     @if($match->prediction) data-href="{{ route('predict.show', $match) }}" @endif>
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
                        @if($match->prediction)
                            <span class="score pred">{{ $match->prediction->predicted_home }}–{{ $match->prediction->predicted_away }}</span>
                            <span class="arrow">→</span>
                        @endif
                        @if($match->status === 'FINISHED')
                            <span class="score real {{ $acc && $acc->exact_score ? 'win' : '' }}">{{ $match->home_score }}–{{ $match->away_score }}</span>
                        @elseif($match->prediction)
                            <span class="fx-todo-tag">nog te spelen</span>
                        @else
                            <span class="fx-todo-tag">voorspelling vergrendeld</span>
                        @endif
                    </span>
                    @php $stageShort = ['GROUP'=>'Groep','R16'=>'1/8','QF'=>'1/4','SF'=>'1/2','THIRD'=>'3e pl.','FINAL'=>'Finale']; @endphp
                    <span class="badge {{ $stageKey === 'FINAL' ? 'green' : '' }}">
                        {{ $match->group_name ?? ($stageShort[$stageKey] ?? $stageKey) }}
                    </span>
                    <span class="t-right"><span class="pts {{ $ptsClass }}">{{ $ptsLabel }}</span></span>
                </div>
                @empty
                <div class="fixture r-todo">
                    <span class="fx-date">–</span>
                    <span class="match-grid">
                        <span class="team home"><span class="team-name dim">TBD</span></span>
                        <span class="fx-vs">vs</span>
                        <span class="team"><span class="team-name dim">TBD</span></span>
                    </span>
                    <span class="fx-compare"><span class="fx-todo-tag">nog niet bekend</span></span>
                    @php $stageShortFb = ['GROUP'=>'Groep','R16'=>'1/8','QF'=>'1/4','SF'=>'1/2','THIRD'=>'3e pl.','FINAL'=>'Finale']; @endphp
                    <span class="badge">{{ $stageShortFb[$stageKey] ?? $stageKey }}</span>
                    <span class="t-right"><span class="pts zero">–</span></span>
                </div>
                @endforelse
            </div>
        </div>
    </section>
    @endforeach
</main>
@endsection
