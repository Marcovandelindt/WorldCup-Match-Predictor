<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'GOALCAST — WK 2026')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body>

<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="{{ route('dashboard') }}">
            <span class="brand-mark"></span>
            <span class="brand-name">GOAL<span>CAST</span></span>
        </a>
        <nav class="nav">
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}"
               href="{{ route('dashboard') }}">Dashboard</a>
            <a class="nav-link {{ request()->routeIs('results.*') ? 'is-active' : '' }}"
               href="{{ route('results.index') }}">Resultaten</a>
            <a class="nav-link {{ request()->routeIs('statistics.*') ? 'is-active' : '' }}"
               href="{{ route('statistics.index') }}">Statistieken</a>
        </nav>
        <div class="topbar-right">
            <span class="season-chip">WK 2026 · 🇺🇸🇨🇦🇲🇽</span>
            <span class="points-pill">
                <span>Punten</span>
                <strong class="mono">{{ $totalPoints ?? 0 }}</strong>
            </span>
        </div>
    </div>
</header>

@yield('content')

<footer class="foot">
    <span>GOALCAST · Poisson-gebaseerde scorelijnvoorspeller voor het WK 2026</span>
    <span class="mono">Model v2.4 · vorm 40 · h2h 30 · fifa 20 · historie 10</span>
</footer>

@stack('scripts')
</body>
</html>
