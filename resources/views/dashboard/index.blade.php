@extends('layouts.app')

@section('title', 'GOALCAST — Dashboard · WK 2026')

@section('content')
<main class="page">
    <div class="page-head">
        <div>
            <p class="eyebrow">Seizoensoverzicht</p>
            <h1 class="page-title">Dashboard</h1>
        </div>
        <div class="page-head-meta">
            <span class="live-dot"></span>
            {{ $currentStageLabel }} · bijgewerkt {{ now()->format('j M Y, H:i') }}
        </div>
    </div>

    {{-- Stat cards --}}
    <section class="stat-grid">
        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-label">Totaal voorspeld</span>
                <span class="stat-ico">🎯</span>
            </div>
            <div class="stat-value mono">{{ $stats['total_predicted'] }}</div>
            <div class="stat-foot">
                <span class="delta flat">{{ $stats['total'] }} gespeeld</span>
                <small>{{ $stats['upcoming'] }} aankomend</small>
            </div>
            <svg class="spark" width="74" height="26" viewBox="0 0 74 26" fill="none">
                <polyline points="0,20 12,16 24,18 36,10 48,12 60,6 74,4" stroke="#16c172" stroke-width="2" fill="none" opacity=".55"/>
            </svg>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-label">Exact correct</span>
                <span class="stat-ico">✅</span>
            </div>
            <div class="stat-value mono">
                {{ $stats['total'] > 0 ? number_format($stats['exact_count'] / $stats['total'] * 100, 1, ',', '') : '0' }}<span class="unit">%</span>
            </div>
            <div class="stat-foot">
                <span class="delta up">▲</span>
                <small>{{ $stats['exact_count'] }} van {{ $stats['total'] }} wedstrijden</small>
            </div>
            <svg class="spark" width="74" height="26" viewBox="0 0 74 26" fill="none">
                <polyline points="0,18 12,20 24,14 36,15 48,9 60,11 74,7" stroke="#16c172" stroke-width="2" fill="none" opacity=".55"/>
            </svg>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-label">Winnaar correct</span>
                <span class="stat-ico">🏆</span>
            </div>
            <div class="stat-value mono">
                {{ $stats['total'] > 0 ? number_format($stats['winner_count'] / $stats['total'] * 100, 1, ',', '') : '0' }}<span class="unit">%</span>
            </div>
            <div class="stat-foot">
                <span class="delta up">▲</span>
                <small>{{ $stats['winner_count'] }} van {{ $stats['total'] }} wedstrijden</small>
            </div>
            <svg class="spark" width="74" height="26" viewBox="0 0 74 26" fill="none">
                <polyline points="0,14 12,12 24,13 36,9 48,10 60,8 74,6" stroke="#16c172" stroke-width="2" fill="none" opacity=".55"/>
            </svg>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-label">Totaal punten</span>
                <span class="stat-ico">⚡</span>
            </div>
            <div class="stat-value mono">{{ $stats['total_points'] }}</div>
            <div class="stat-foot">
                <span class="delta up">▲</span>
                <small>gem. {{ $stats['total'] > 0 ? number_format($stats['total_points'] / $stats['total'], 1, ',', '') : '0' }} / wedstrijd</small>
            </div>
            <svg class="spark" width="74" height="26" viewBox="0 0 74 26" fill="none">
                <polyline points="0,22 12,19 24,17 36,14 48,12 60,7 74,3" stroke="#16c172" stroke-width="2" fill="none" opacity=".55"/>
            </svg>
        </div>
    </section>

    {{-- Upcoming matches --}}
    @php
        $stageBadge = ['GROUP'=>'Groepsfase','R16'=>'Achtste F.','QF'=>'Kwartfinale','SF'=>'Halve finale','THIRD'=>'3e Plaats','FINAL'=>'Finale'];
    @endphp
    <section class="section">
        <div class="section-head">
            <h2 class="section-title">
                Aankomende wedstrijden
                <span class="count">{{ $upcoming->count() }}</span>
            </h2>
            <span class="section-sub">{{ $currentStageLabel }}</span>
        </div>
        <div class="card">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th style="width:150px">Datum</th>
                            <th>Wedstrijd</th>
                            <th style="width:120px">Fase</th>
                            <th style="width:220px" class="t-right">Actie</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($upcoming as $match)
                        <tr>
                            <td class="num dim">
                                {{ $match->match_date->locale('nl')->isoFormat('ddd D MMM') }}<br>
                                <span class="dim">{{ $match->match_date->format('H:i') }}</span>
                            </td>
                            <td>
                                <div class="match-grid">
                                    <span class="team home">
                                        <a class="team-name" href="{{ route('teams.show', $match->homeTeam) }}">{{ $match->homeTeam->name }}</a>
                                        <span class="flag fi fi-{{ $match->homeTeam->flag_emoji ?? 'xx' }}"></span>
                                    </span>
                                    <span class="vs">vs</span>
                                    <span class="team">
                                        <span class="flag fi fi-{{ $match->awayTeam->flag_emoji ?? 'xx' }}"></span>
                                        <a class="team-name" href="{{ route('teams.show', $match->awayTeam) }}">{{ $match->awayTeam->name }}</a>
                                    </span>
                                </div>
                            </td>
                            <td><span class="badge">{{ $stageBadge[$match->stage] ?? $match->stage }}</span></td>
                            <td class="t-right">
                                @if($match->prediction)
                                    <a class="btn btn-ghost btn-sm" href="{{ route('predict.show', $match) }}">
                                        <span class="ico">📊</span> Bekijk voorspelling
                                    </a>
                                @else
                                    <form method="POST" action="{{ route('predict.generate', $match) }}" style="display:inline">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-sm" data-generate>
                                            <span class="ico">⚡</span> Genereer voorspelling
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="dim" style="padding:24px 16px; text-align:center">
                                Geen aankomende wedstrijden gevonden.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- Played matches --}}
    <section class="section">
        <div class="section-head">
            <h2 class="section-title">
                Gespeelde wedstrijden
                <span class="count">{{ $finished->count() }}</span>
            </h2>
            <div class="legend">
                <span class="legend-item"><span class="status-dot exact"></span> Exacte score</span>
                <span class="legend-item"><span class="status-dot winner"></span> Winnaar correct</span>
                <span class="legend-item"><span class="status-dot wrong"></span> Fout</span>
            </div>
        </div>
        <div class="card">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th style="width:96px">Datum</th>
                            <th>Wedstrijd</th>
                            <th style="width:90px" class="t-center">Voorspeld</th>
                            <th style="width:90px" class="t-center">Uitslag</th>
                            <th style="width:64px" class="t-right">Punten</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($finished as $match)
                        @php
                            $acc = $match->accuracy;
                            $rowClass = '';
                            $ptsClass = 'zero';
                            $pts = '–';
                            if ($acc) {
                                $rowClass = $acc->exact_score ? 'r-exact' : ($acc->correct_winner ? 'r-winner' : 'r-wrong');
                                $pts = '+' . $acc->points_earned;
                                $ptsClass = $acc->points_earned >= 3 ? 'pos' : ($acc->points_earned > 0 ? 'mid' : 'zero');
                            }
                        @endphp
                        <tr class="{{ $rowClass }}"
                            @if($match->prediction) data-href="{{ route('predict.show', $match) }}" @endif>
                            <td class="num dim">{{ $match->match_date->format('j M') }}</td>
                            <td>
                                <div class="match-grid">
                                    <span class="team home">
                                        <a class="team-name" href="{{ route('teams.show', $match->homeTeam) }}">{{ $match->homeTeam->name }}</a>
                                        <span class="flag fi fi-{{ $match->homeTeam->flag_emoji ?? 'xx' }}"></span>
                                    </span>
                                    <span class="vs">{{ $match->home_score }}–{{ $match->away_score }}</span>
                                    <span class="team">
                                        <span class="flag fi fi-{{ $match->awayTeam->flag_emoji ?? 'xx' }}"></span>
                                        <a class="team-name" href="{{ route('teams.show', $match->awayTeam) }}">{{ $match->awayTeam->name }}</a>
                                    </span>
                                </div>
                            </td>
                            <td class="t-center">
                                @if($match->prediction)
                                    <span class="score pred">{{ $match->prediction->predicted_home }}–{{ $match->prediction->predicted_away }}</span>
                                @else
                                    <span class="dim">–</span>
                                @endif
                            </td>
                            <td class="t-center">
                                <span class="score real {{ $acc && $acc->exact_score ? 'win' : '' }}">
                                    {{ $match->home_score }}–{{ $match->away_score }}
                                </span>
                            </td>
                            <td class="t-right">
                                <span class="pts {{ $ptsClass }}">{{ $pts }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="dim" style="padding:24px 16px; text-align:center">
                                Nog geen gespeelde wedstrijden.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>
@endsection
