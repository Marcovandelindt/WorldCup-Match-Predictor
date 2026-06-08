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
                <span class="hero-flag fi fi-{{ $match->homeTeam->flag_emoji ?? 'xx' }}"></span>
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
                <span class="hero-flag fi fi-{{ $match->awayTeam->flag_emoji ?? 'xx' }}"></span>
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
                                <span class="flag fi fi-{{ $match->homeTeam->flag_emoji ?? 'xx' }}"></span>
                                {{ $match->homeTeam->name }}
                            </span>
                        </div>
                        <span class="val mono">{{ number_format($lambdaHome, 2, ',', '') }}<small> xG</small></span>
                    </div>
                    <div class="lambda away">
                        <div class="meta">
                            <span class="greek">λ uit</span>
                            <span class="team-mini">
                                <span class="flag fi fi-{{ $match->awayTeam->flag_emoji ?? 'xx' }}"></span>
                                {{ $match->awayTeam->name }}
                            </span>
                        </div>
                        <span class="val mono">{{ number_format($lambdaAway, 2, ',', '') }}<small> xG</small></span>
                    </div>
                    <div class="formula">
                        <span class="k">λ</span> = Σ (gewicht<sub>i</sub> × component<sub>i</sub>)<br>
                        P(score) = Poisson(λ<sub>t</sub>) × Poisson(λ<sub>u</sub>)
                    </div>
                </div>

                <div class="weights">
                    @php
                        $bd = $prediction->breakdown ?? [];
                        $hasH2h = $bd['has_h2h'] ?? false;
                        $weights = [
                            ['label' => 'Vorm',            'pct' => $bd['home']['form']['weight'] ?? ($hasH2h ? 0.40 : 0.70)],
                            ['label' => 'Onderling (H2H)', 'pct' => $bd['home']['h2h']['weight']  ?? ($hasH2h ? 0.30 : 0.00)],
                            ['label' => 'Elo-rating',      'pct' => $bd['home']['elo']['weight']  ?? ($bd['home']['fifa']['weight'] ?? 0.20)],
                            ['label' => 'WK-historie',     'pct' => $bd['home']['wc']['weight']   ?? 0.10],
                        ];
                    @endphp
                    @foreach($weights as $w)
                    @if($w['pct'] > 0)
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
                    @endif
                    @endforeach

                    <div class="formula" style="margin-top:18px">
                        Elo: {{ $match->homeTeam->name }} {{ number_format($bd['home']['elo']['rating'] ?? 1500, 0, ',', '.') }} ·
                             {{ $match->awayTeam->name }} {{ number_format($bd['away']['elo']['rating'] ?? 1500, 0, ',', '.') }}<br>
                        WK-gemiddelde: {{ number_format($bd['wc_avg'] ?? 1.30, 2, ',', '') }} goals/wedstrijd<br>
                        Berekend op: {{ ($prediction->generated_at ?? now())->format('j M Y, H:i') }}
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Formula breakdown --}}
    @if(!empty($bd['home']))
    <section class="section">
        <div class="section-head">
            <h2 class="section-title">Formule-breakdown</h2>
            <span class="section-sub">Hoe elk component bijdraagt aan λ (verwachte doelpunten)</span>
        </div>
        <div class="breakdown-grid">
            @foreach(['home' => $match->homeTeam, 'away' => $match->awayTeam] as $side => $team)
            @php $bSide = $bd[$side]; @endphp
            <div class="breakdown-card card card-pad">
                <div class="bd-header">
                    <span class="flag fi fi-{{ $team->flag_emoji ?? 'xx' }}"></span>
                    <span class="bd-team">{{ $team->name }}</span>
                    <span class="bd-lambda mono">λ = {{ number_format($bSide['lambda_total'], 4, ',', '') }}</span>
                </div>
                <div class="bd-table-wrap">
                <table class="bd-table">
                    <thead>
                        <tr>
                            <th>Component</th>
                            <th>Formule</th>
                            <th>λ</th>
                            <th>Gewicht</th>
                            <th>Bijdrage</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $f = $bSide['form']; @endphp
                        <tr>
                            <td>
                                <b>Vorm</b>
                                <small>{{ $f['matches'] }} wed. · gem. {{ number_format($f['avg_scored'], 2, ',', '') }}/{{ number_format($f['avg_conceded'], 2, ',', '') }}</small>
                            </td>
                            <td class="mono">{{ number_format($f['attack'], 4, ',', '') }} × {{ number_format($f['defense'], 4, ',', '') }} × {{ number_format($bd['wc_avg'], 2, ',', '') }}</td>
                            <td class="mono">{{ number_format($f['lambda'], 4, ',', '') }}</td>
                            <td class="mono">{{ round($f['weight'] * 100) }}%</td>
                            <td class="mono bd-contrib">{{ number_format($f['contribution'], 4, ',', '') }}</td>
                        </tr>

                        @if($hasH2h && !empty($bSide['h2h']))
                        @php $h = $bSide['h2h']; @endphp
                        <tr>
                            <td>
                                <b>Onderling (H2H)</b>
                                <small>{{ $h['matches'] }} wed.</small>
                            </td>
                            <td class="mono">{{ number_format($h['attack'], 4, ',', '') }} × {{ number_format($h['defense'], 4, ',', '') }} × {{ number_format($bd['wc_avg'], 2, ',', '') }}</td>
                            <td class="mono">{{ number_format($h['lambda'], 4, ',', '') }}</td>
                            <td class="mono">{{ round($h['weight'] * 100) }}%</td>
                            <td class="mono bd-contrib">{{ number_format($h['contribution'], 4, ',', '') }}</td>
                        </tr>
                        @endif

                        @php $ei = $bSide['elo'] ?? $bSide['fifa'] ?? null; @endphp
                        @if($ei)
                        <tr>
                            <td>
                                <b>Elo-rating</b>
                                <small>{{ number_format($ei['rating'] ?? 0, 0, ',', '.') }} punten</small>
                            </td>
                            <td class="mono">λ<sub>vorm</sub> × {{ number_format($ei['factor'], 4, ',', '') }}</td>
                            <td class="mono">{{ number_format($ei['lambda'], 4, ',', '') }}</td>
                            <td class="mono">{{ round($ei['weight'] * 100) }}%</td>
                            <td class="mono bd-contrib">{{ number_format($ei['contribution'], 4, ',', '') }}</td>
                        </tr>
                        @endif

                        @php $wc = $bSide['wc']; @endphp
                        <tr>
                            <td>
                                <b>WK-historie</b>
                                <small>gem. {{ number_format($wc['avg_scored'], 2, ',', '') }}/{{ number_format($wc['avg_conceded'], 2, ',', '') }}</small>
                            </td>
                            <td class="mono">{{ number_format($wc['attack'], 4, ',', '') }} × {{ number_format($wc['defense'], 4, ',', '') }} × {{ number_format($bd['wc_avg'], 2, ',', '') }}</td>
                            <td class="mono">{{ number_format($wc['lambda'], 4, ',', '') }}</td>
                            <td class="mono">{{ round($wc['weight'] * 100) }}%</td>
                            <td class="mono bd-contrib">{{ number_format($wc['contribution'], 4, ',', '') }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="bd-total">
                            <td colspan="3"><b>Totaal verwachte doelpunten</b></td>
                            <td class="mono">100%</td>
                            <td class="mono bd-contrib"><b>{{ number_format($bSide['lambda_total'], 4, ',', '') }}</b></td>
                        </tr>
                    </tfoot>
                </table>
                </div>
            </div>
            @endforeach
        </div>
    </section>
    @endif
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
