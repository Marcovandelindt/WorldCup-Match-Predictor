@extends('layouts.app')

@section('title', $team->name . ' — GOALCAST')

@section('content')
<main class="page">

    {{-- Back link --}}
    <div style="margin-bottom:16px">
        <a href="javascript:history.back()" class="btn btn-ghost btn-sm">← Terug</a>
    </div>

    {{-- Team hero --}}
    <div class="team-hero" style="margin-bottom:16px">
        <span class="crest fi fi-{{ $team->flag_emoji ?? 'xx' }}"></span>

        <div class="th-info">
            <div class="th-name">{{ $team->name }}</div>

            <div class="th-meta">
                @if($team->short_name && $team->short_name !== $team->name)
                    <span class="badge">{{ $team->short_name }}</span>
                @endif
                @if($team->fifa_code)
                    <span class="badge">{{ $team->fifa_code }}</span>
                @endif
                @if($groupName)
                    <span class="badge green">{{ $groupName }}</span>
                @endif
                @if($team->confederation)
                    <span class="badge">{{ $team->confederation }}</span>
                @endif
                @if($team->wc_appearances)
                    <span class="badge">{{ $team->wc_appearances }}× WK</span>
                @endif
                @if($team->wc_best_result)
                    <span class="badge">Beste: {{ $team->wc_best_result }}</span>
                @endif
            </div>

            @if($form->isNotEmpty())
            <div class="th-form">
                <span class="lbl">Vorm</span>
                <div class="form-pills">
                    @foreach($form as $f)
                    @php
                        $fpClass = match($f->result) {
                            'WIN'  => 'w',
                            'DRAW' => 'g',
                            'LOSS' => 'v',
                            default => 'g',
                        };
                        $fpLetter = match($f->result) {
                            'WIN'  => 'W',
                            'DRAW' => 'G',
                            'LOSS' => 'V',
                            default => '?',
                        };
                    @endphp
                    <span class="fp {{ $fpClass }}">{{ $fpLetter }}</span>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <div class="team-facts">
            <div class="team-fact">
                <span class="v{{ $team->fifa_ranking ? '' : '' }}">{{ $team->fifa_ranking ?? '–' }}</span>
                <span class="l">FIFA rang</span>
            </div>
            <div class="team-fact">
                <span class="v green">{{ $goalsScored }}</span>
                <span class="l">Goals voor</span>
            </div>
            <div class="team-fact">
                <span class="v">{{ $goalsConceded }}</span>
                <span class="l">Goals tegen</span>
            </div>
        </div>
    </div>

    {{-- Split layout: Recent matches + WK 2026 --}}
    <div class="split-2">

        {{-- Recent matches --}}
        <section class="section">
            <div class="section-head">
                <h2 class="section-title">
                    Recente wedstrijden
                    <span class="count">{{ $recentMatches->count() }}</span>
                </h2>
            </div>
            <div class="card">
                <div class="fixtures">
                    @forelse($recentMatches as $match)
                    @php
                        $fixtureClass = match($match->result) {
                            'WIN'  => 'r-w',
                            'DRAW' => 'r-d',
                            'LOSS' => 'r-l',
                            default => 'r-d',
                        };
                        $outcomeClass = match($match->result) {
                            'WIN'  => 'w',
                            'DRAW' => 'g',
                            'LOSS' => 'v',
                            default => 'g',
                        };
                        $outcomeLetter = match($match->result) {
                            'WIN'  => 'W',
                            'DRAW' => 'G',
                            'LOSS' => 'V',
                            default => '?',
                        };
                        $opponentTeam = $teamsByApiId[$match->opponent_api_id] ?? null;
                    @endphp
                    <div class="fixture {{ $fixtureClass }}">
                        <span class="fx-date">{{ $match->match_date->format('j M') }}</span>
                        <span class="match-grid">
                            <span class="team home">
                                <span class="team-name">{{ $team->name }}</span>
                                <span class="flag fi fi-{{ $team->flag_emoji ?? 'xx' }}"></span>
                            </span>
                            <span class="fx-vs">{{ $match->goals_scored }}–{{ $match->goals_conceded }}</span>
                            <span class="team">
                                <span class="flag fi fi-{{ $opponentTeam?->flag_emoji ?? 'xx' }}"></span>
                                <span class="team-name">{{ $match->opponent_name }}</span>
                            </span>
                        </span>
                        <span>
                            @if($match->competition)
                                <span class="badge">{{ $match->competition }}</span>
                            @endif
                        </span>
                        <span class="outcome {{ $outcomeClass }}">{{ $outcomeLetter }}</span>
                    </div>
                    @empty
                    <div class="fixture r-todo">
                        <span class="fx-date">–</span>
                        <span class="match-grid">
                            <span class="team home"><span class="dim">Geen data</span></span>
                            <span class="fx-vs">–</span>
                            <span class="team"><span class="dim">–</span></span>
                        </span>
                        <span></span>
                        <span></span>
                    </div>
                    @endforelse
                </div>
            </div>
        </section>

        {{-- WK 2026 matches --}}
        <section class="section">
            <div class="section-head">
                <h2 class="section-title">WK 2026</h2>
            </div>

            @if($wkMatchesByStage->isEmpty())
                <div class="card card-pad">
                    <p class="dim">Geen WK 2026-wedstrijden gevonden.</p>
                </div>
            @else
                @foreach($stageLabels as $stageKey => $stageLabel)
                @php $stagMatches = $wkMatchesByStage[$stageKey] ?? collect(); @endphp
                @if($stagMatches->isNotEmpty())
                <div class="phase-group">
                    <div class="phase-head">
                        <h3>{{ $stageLabel }}</h3>
                        <span class="phase-line"></span>
                    </div>
                    <div class="card">
                        <div class="fixtures">
                            @foreach($stagMatches as $match)
                            @php
                                $isHome = $match->home_team_id === $team->id;
                                $opponent = $isHome ? $match->awayTeam : $match->homeTeam;

                                $acc = $match->accuracy;
                                if ($acc) {
                                    $fixtureClass = $acc->exact_score ? 'r-exact' : ($acc->correct_winner ? 'r-winner' : 'r-wrong');
                                } elseif ($match->status === 'SCHEDULED' || $match->status === 'POSTPONED') {
                                    $fixtureClass = 'r-todo';
                                } else {
                                    $fixtureClass = 'r-todo';
                                }
                            @endphp
                            <div class="fixture {{ $fixtureClass }}"
                                 @if($match->prediction) data-href="{{ route('predict.show', $match) }}" @endif>
                                <span class="fx-date">{{ $match->match_date->format('j M') }}</span>
                                <span class="match-grid">
                                    <span class="team home">
                                        @if($isHome)
                                            <span class="team-name">{{ $team->name }}</span>
                                            <span class="flag fi fi-{{ $team->flag_emoji ?? 'xx' }}"></span>
                                        @else
                                            <span class="team-name">{{ $opponent?->name ?? 'TBD' }}</span>
                                            <span class="flag fi fi-{{ $opponent?->flag_emoji ?? 'xx' }}"></span>
                                        @endif
                                    </span>
                                    <span class="fx-vs">
                                        @if($match->status === 'FINISHED')
                                            @if($isHome)
                                                {{ $match->home_score }}–{{ $match->away_score }}
                                            @else
                                                {{ $match->away_score }}–{{ $match->home_score }}
                                            @endif
                                        @else
                                            vs
                                        @endif
                                    </span>
                                    <span class="team">
                                        @if($isHome)
                                            <span class="flag fi fi-{{ $opponent?->flag_emoji ?? 'xx' }}"></span>
                                            <span class="team-name">{{ $opponent?->name ?? 'TBD' }}</span>
                                        @else
                                            <span class="flag fi fi-{{ $team->flag_emoji ?? 'xx' }}"></span>
                                            <span class="team-name">{{ $team->name }}</span>
                                        @endif
                                    </span>
                                </span>
                                <span class="fx-compare">
                                    @if($match->prediction)
                                        @if($isHome)
                                            <span class="score pred">{{ $match->prediction->predicted_home }}–{{ $match->prediction->predicted_away }}</span>
                                        @else
                                            <span class="score pred">{{ $match->prediction->predicted_away }}–{{ $match->prediction->predicted_home }}</span>
                                        @endif
                                        @if($match->status !== 'FINISHED')
                                            <span class="arrow">→</span>
                                        @endif
                                    @endif
                                    @if($match->status === 'FINISHED' && $acc)
                                        <span class="pts {{ $acc->points_earned >= 3 ? 'pos' : ($acc->points_earned > 0 ? 'mid' : 'zero') }}">
                                            +{{ $acc->points_earned }}
                                        </span>
                                    @elseif($match->status === 'SCHEDULED' && !$match->prediction)
                                        <span class="fx-todo-tag">nog te spelen</span>
                                    @endif
                                </span>
                                <span class="badge {{ $stageKey === 'FINAL' ? 'green' : '' }}">
                                    @if($stageKey === 'GROUP' && $match->group_name)
                                        {{ str_replace('GROUP_', '', $match->group_name) }}
                                    @else
                                        {{ ['GROUP'=>'Groep','R16'=>'1/8','QF'=>'1/4','SF'=>'1/2','THIRD'=>'3e pl.','FINAL'=>'Finale'][$stageKey] ?? $stageKey }}
                                    @endif
                                </span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif
                @endforeach
            @endif
        </section>

    </div>{{-- /.split-2 --}}

    {{-- WK-resultaten per editie --}}
    @if($wcMatches->isNotEmpty())
    <section class="section" style="margin-top:16px">
        <div class="section-head">
            <h2 class="section-title">WK-geschiedenis</h2>
            <span class="section-sub">Resultaten per editie vanaf 1994</span>
        </div>
        @foreach($wcMatches as $year => $matches)
        <div class="phase-group">
            <div class="phase-head">
                <h3>WK {{ $year }}</h3>
                <span class="phase-line"></span>
            </div>
            <div class="card">
                <div class="fixtures">
                    @foreach($matches as $match)
                    @php
                        $fc = match($match->result) { 'WIN' => 'r-w', 'DRAW' => 'r-d', 'LOSS' => 'r-l', default => 'r-d' };
                        $oc = match($match->result) { 'WIN' => 'w', 'DRAW' => 'g', 'LOSS' => 'v', default => 'g' };
                        $ol = match($match->result) { 'WIN' => 'W', 'DRAW' => 'G', 'LOSS' => 'V', default => '?' };
                    @endphp
                    <div class="fixture {{ $fc }}">
                        <span class="fx-date">{{ \Carbon\Carbon::parse($match->match_date)->format('j M') }}</span>
                        <span class="match-grid">
                            <span class="team home">
                                <span class="team-name">{{ $team->name }}</span>
                                <span class="flag fi fi-{{ $team->flag_emoji ?? 'xx' }}"></span>
                            </span>
                            <span class="fx-vs">{{ $match->goals_scored }}–{{ $match->goals_conceded }}</span>
                            <span class="team">
                                <span class="flag fi fi-xx"></span>
                                <span class="team-name">{{ $match->opponent_name }}</span>
                            </span>
                        </span>
                        <span></span>
                        <span class="outcome {{ $oc }}">{{ $ol }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endforeach
    </section>
    @endif

    {{-- Stats: FIFA ranking + WK-geschiedenis --}}
    @if($team->fifa_ranking || $team->avg_goals_scored_wc > 0 || $team->wc_appearances)
    <section class="section" style="margin-top:16px">
        <div class="section-head">
            <h2 class="section-title">Statistieken</h2>
        </div>
        <div class="card card-pad">
            <div class="rank-meta">
                @if($team->fifa_ranking)
                <div class="rm">
                    <b>#{{ $team->fifa_ranking }}</b>
                    <span>FIFA ranking</span>
                </div>
                @endif
                @if($team->confederation)
                <div class="rm">
                    <b>{{ $team->confederation }}</b>
                    <span>Confederatie</span>
                </div>
                @endif
                @if($team->wc_appearances)
                <div class="rm">
                    <b>{{ $team->wc_appearances }}</b>
                    <span>WK-deelnames</span>
                </div>
                @endif
                @if($team->wc_best_result)
                <div class="rm">
                    <b>{{ $team->wc_best_result }}</b>
                    <span>Beste WK-resultaat</span>
                </div>
                @endif
                @if($team->avg_goals_scored_wc > 0)
                <div class="rm">
                    <b class="green">{{ number_format($team->avg_goals_scored_wc, 2, ',', '') }}</b>
                    <span>gem. goals/wed. op WK</span>
                </div>
                @endif
                @if($team->avg_goals_conceded_wc > 0)
                <div class="rm">
                    <b>{{ number_format($team->avg_goals_conceded_wc, 2, ',', '') }}</b>
                    <span>gem. tegend./wed. op WK</span>
                </div>
                @endif
            </div>
        </div>
    </section>
    @endif

</main>
@endsection
