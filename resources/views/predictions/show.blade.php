@extends('layouts.app')

@section('title', 'GOALCAST — Voorspelling · ' . $match->homeTeam->name . ' vs ' . $match->awayTeam->name)

@section('content')
<main class="page">
    <a class="backlink" href="{{ route('dashboard') }}">← Terug naar dashboard</a>

    {{-- Hero: Finale voorspelling --}}
    <section class="predict-hero">
        <div class="hero-meta">
            @php $stageNames = ['GROUP'=>'Groepsfase','R16'=>'Achtste finale','QF'=>'Kwartfinale','SF'=>'Halve finale','THIRD'=>'3e Plaats','FINAL'=>'Finale']; @endphp
        <span class="badge green">{{ $stageNames[$match->stage] ?? $match->stage }}</span>
            <span class="badge">{{ $match->match_date->locale('nl')->isoFormat('ddd D MMM YYYY · HH:mm') }}</span>
            @if($match->venue ?? false)
                <span class="badge">{{ $match->venue }}</span>
            @endif
        </div>

        <div class="hero-teams">
            <div class="hero-team">
                <span class="hero-flag">{{ $match->homeTeam->flag_emoji ?? '🏴' }}</span>
                <span class="hero-team-name">{{ $match->homeTeam->name }}</span>
            </div>
            <div class="hero-center">
                @if($prediction)
                    <div class="hero-score mono">
                        <span>{{ $prediction->predicted_home ?? $prediction['prediction']['home'] ?? '?' }}</span>
                        <span class="dash">–</span>
                        <span>{{ $prediction->predicted_away ?? $prediction['prediction']['away'] ?? '?' }}</span>
                    </div>
                    <div class="hero-prob">
                        @php
                            $conf = $prediction->confidence_pct ?? $prediction['prediction']['probability'] ?? 0;
                        @endphp
                        <span class="big mono">{{ number_format($conf, 1, ',', '') }}%</span>
                        <span class="lbl">kans op exact deze uitslag</span>
                    </div>
                @else
                    <div class="hero-score mono"><span class="dim">?</span><span class="dash">–</span><span class="dim">?</span></div>
                @endif
            </div>
            <div class="hero-team">
                <span class="hero-flag">{{ $match->awayTeam->flag_emoji ?? '🏴' }}</span>
                <span class="hero-team-name">{{ $match->awayTeam->name }}</span>
            </div>
        </div>

        @if($prediction)
        @php
            $scorelines = $prediction->top_scorelines ?? $prediction['scorelines'] ?? [];
            $homeWin = 0; $draw = 0; $awayWin = 0;
            foreach ($scorelines as $s) {
                if ($s['home'] > $s['away'])       $homeWin += $s['probability'];
                elseif ($s['home'] === $s['away'])  $draw    += $s['probability'];
                else                               $awayWin += $s['probability'];
            }
        @endphp
        <div class="wdl">
            <div class="wdl-seg wdl-home">
                <div class="p mono">{{ number_format($homeWin, 1, ',', '') }}%</div>
                <div class="l">{{ $match->homeTeam->name }} wint</div>
            </div>
            <div class="wdl-seg wdl-draw">
                <div class="p mono">{{ number_format($draw, 1, ',', '') }}%</div>
                <div class="l">Gelijkspel</div>
            </div>
            <div class="wdl-seg wdl-away">
                <div class="p mono">{{ number_format($awayWin, 1, ',', '') }}%</div>
                <div class="l">{{ $match->awayTeam->name }} wint</div>
            </div>
        </div>
        @endif
    </section>

    @if($prediction)
    @php
        $scorelines = $prediction->top_scorelines ?? $prediction['scorelines'] ?? [];
        $lambdaHome = $prediction->lambda_home ?? $prediction['lambda_home'] ?? 0;
        $lambdaAway = $prediction->lambda_away ?? $prediction['lambda_away'] ?? 0;
        $maxProb    = !empty($scorelines) ? max(array_column($scorelines, 'probability')) : 1;
    @endphp

    {{-- Top 10 scorelines --}}
    <section class="section">
        <div class="section-head">
            <h2 class="section-title">Top 10 waarschijnlijke scorelijnen</h2>
            <span class="section-sub">
                Poisson-verdeling · λ<sub>thuis</sub> {{ number_format($lambdaHome, 2, ',', '') }} · λ<sub>uit</sub> {{ number_format($lambdaAway, 2, ',', '') }}
            </span>
        </div>
        <div class="card card-pad">
            <div class="bars">
                @foreach($scorelines as $i => $s)
                @php $w = $maxProb > 0 ? round($s['probability'] / $maxProb * 100) : 0; @endphp
                <div class="bar-row {{ $i === 0 ? 'top' : '' }}">
                    <span class="bar-score">{{ $s['home'] }}–{{ $s['away'] }}</span>
                    <span class="bar-track">
                        <span class="bar-fill" data-w="{{ $w }}" style="width:0"></span>
                    </span>
                    <span class="bar-pct">{{ number_format($s['probability'], 1, ',', '') }}%</span>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Technical calculation --}}
    <section class="section">
        <div class="section-head">
            <h2 class="section-title">Technische berekening</h2>
            <span class="section-sub">Verwachte doelpunten (λ) per team &amp; gewichtenverdeling</span>
        </div>
        <div class="card card-pad">
            <div class="calc-grid">
                <div class="lambda-box">
                    <div class="lambda home">
                        <div class="meta">
                            <span class="greek">λ thuis</span>
                            <span class="team-mini">
                                <span class="flag">{{ $match->homeTeam->flag_emoji ?? '🏴' }}</span>
                                {{ $match->homeTeam->name }}
                            </span>
                        </div>
                        <span class="val mono">{{ number_format($lambdaHome, 2, ',', '') }}<small> xG</small></span>
                    </div>
                    <div class="lambda away">
                        <div class="meta">
                            <span class="greek">λ uit</span>
                            <span class="team-mini">
                                <span class="flag">{{ $match->awayTeam->flag_emoji ?? '🏴' }}</span>
                                {{ $match->awayTeam->name }}
                            </span>
                        </div>
                        <span class="val mono">{{ number_format($lambdaAway, 2, ',', '') }}<small> xG</small></span>
                    </div>
                    <div class="formula">
                        <span class="k">λ</span> = Σ (gewicht<sub>i</sub> × index<sub>i</sub>)<br>
                        P(score) = Poisson(λ<sub>t</sub>) × Poisson(λ<sub>u</sub>)
                    </div>
                </div>

                <div class="weights">
                    @php
                        $weights = [
                            ['label' => 'Vorm',           'key' => 'weight_form',       'pct' => $prediction->weight_form       ?? 0.40],
                            ['label' => 'Onderling (H2H)', 'key' => 'weight_h2h',        'pct' => $prediction->weight_h2h        ?? 0.30],
                            ['label' => 'FIFA-ranking',   'key' => 'weight_fifa',       'pct' => $prediction->weight_fifa       ?? 0.20],
                            ['label' => 'WK-historie',    'key' => 'weight_wc_history', 'pct' => $prediction->weight_wc_history ?? 0.10],
                        ];
                    @endphp
                    @foreach($weights as $w)
                    <div class="weight-row">
                        <div class="weight-name">
                            <b>{{ $w['label'] }}</b>
                            <span class="pct mono">{{ round($w['pct'] * 100) }}%</span>
                        </div>
                        <div class="weight-vis">
                            <span class="weight-track">
                                <span class="fill" data-w="{{ round($w['pct'] * 100) }}" style="width:0"></span>
                            </span>
                        </div>
                    </div>
                    @endforeach

                    <div class="formula" style="margin-top:18px">
                        FIFA: {{ $match->homeTeam->name }} #{{ $match->homeTeam->fifa_ranking ?? '?' }} ·
                              {{ $match->awayTeam->name }} #{{ $match->awayTeam->fifa_ranking ?? '?' }}<br>
                        H2H: gebaseerd op historische ontmoetingen<br>
                        Berekend op: {{ ($prediction->generated_at ?? now())->format('j M Y, H:i') }}
                    </div>
                </div>
            </div>
        </div>
    </section>
    @else
    <div class="section">
        <div class="card card-pad" style="text-align:center; padding:40px">
            <p class="dim">Nog geen voorspelling gegenereerd voor deze wedstrijd.</p>
            <form method="POST" action="{{ route('predict.generate', $match) }}" style="margin-top:16px">
                @csrf
                <button type="submit" class="btn btn-primary" data-generate>
                    <span class="ico">⚡</span> Genereer voorspelling
                </button>
            </form>
        </div>
    </div>
    @endif
</main>
@endsection
