<!DOCTYPE html>
@php
    $locale = app()->getLocale();
    $dir    = \LaraSaaS\Facades\LaraSaaS::textDirection($locale);
    $accent = \LaraSaaS\Facades\LaraSaaS::accentColor();
@endphp
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>@yield('title', config('app.name', 'Laravel'))</title>
    <style>
        :root {
            --ls-accent: {{ $accent }};
            --ls-bg: #f6f7f9;
            --ls-card: #ffffff;
            --ls-border: #e5e7eb;
            --ls-text: #111827;
            --ls-muted: #6b7280;
            --ls-ok: #16a34a;
            --ls-warn: #d97706;
            --ls-radius: 14px;
            --ls-shadow: 0 1px 2px rgba(16, 24, 40, .05), 0 8px 24px rgba(16, 24, 40, .06);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --ls-bg: #0b1020;
                --ls-card: #111827;
                --ls-border: #1f2937;
                --ls-text: #f3f4f6;
                --ls-muted: #9ca3af;
                --ls-shadow: 0 1px 2px rgba(0, 0, 0, .4), 0 8px 24px rgba(0, 0, 0, .35);
            }
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            background: var(--ls-bg);
            color: var(--ls-text);
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
            font-size: 15px;
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
        }
        .ls-shell { max-width: 1060px; margin: 0 auto; padding: 20px 20px 40px; }
        .ls-header {
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; padding: 6px 2px 18px;
        }
        .ls-nav { display: flex; gap: 4px; }
        .ls-nav a {
            color: var(--ls-muted); text-decoration: none; font-weight: 500;
            padding: 7px 12px; border-radius: 9px; font-size: 14px;
        }
        .ls-nav a:hover { color: var(--ls-text); background: color-mix(in srgb, var(--ls-text) 6%, transparent); }
        .ls-nav a.is-active { color: var(--ls-accent); background: color-mix(in srgb, var(--ls-accent) 12%, transparent); }
        .ls-notice {
            margin: 0 0 18px; padding: 12px 16px; border-radius: var(--ls-radius);
            background: color-mix(in srgb, var(--ls-ok) 12%, transparent);
            border: 1px solid color-mix(in srgb, var(--ls-ok) 35%, transparent);
            color: var(--ls-text); font-weight: 500;
        }
        .ls-notice.is-warn {
            background: color-mix(in srgb, var(--ls-warn) 12%, transparent);
            border-color: color-mix(in srgb, var(--ls-warn) 38%, transparent);
        }
        .ls-hero { text-align: center; margin: 8px auto 26px; max-width: 640px; }
        .ls-hero h1 { margin: 0 0 8px; font-size: 28px; letter-spacing: -.02em; }
        .ls-hero p { margin: 0; color: var(--ls-muted); font-size: 15.5px; }
        .ls-toggle {
            display: inline-flex; gap: 4px; margin: 18px 0 26px; padding: 4px;
            background: color-mix(in srgb, var(--ls-text) 6%, transparent);
            border-radius: 11px;
        }
        .ls-toggle a {
            padding: 7px 16px; border-radius: 8px; text-decoration: none;
            color: var(--ls-muted); font-weight: 600; font-size: 13.5px;
        }
        .ls-toggle a.is-active {
            background: var(--ls-card); color: var(--ls-text);
            box-shadow: 0 1px 3px rgba(16, 24, 40, .12);
        }
        .ls-grid {
            display: grid; gap: 16px;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            align-items: stretch;
        }
        .ls-card {
            background: var(--ls-card); border: 1px solid var(--ls-border);
            border-radius: var(--ls-radius); padding: 22px; box-shadow: var(--ls-shadow);
            display: flex; flex-direction: column;
        }
        .ls-card.is-highlight {
            border-color: color-mix(in srgb, var(--ls-accent) 55%, var(--ls-border));
            box-shadow: 0 0 0 1px color-mix(in srgb, var(--ls-accent) 35%, transparent), var(--ls-shadow);
        }
        .ls-badge {
            display: inline-block; font-size: 11.5px; font-weight: 700; letter-spacing: .02em;
            padding: 3px 9px; border-radius: 999px;
            background: color-mix(in srgb, var(--ls-accent) 14%, transparent);
            color: var(--ls-accent); margin-inline-end: 6px;
        }
        .ls-badge.is-muted { background: color-mix(in srgb, var(--ls-text) 8%, transparent); color: var(--ls-muted); }
        .ls-badge.is-ok { background: color-mix(in srgb, var(--ls-ok) 14%, transparent); color: var(--ls-ok); }
        .ls-badge.is-warn { background: color-mix(in srgb, var(--ls-warn) 14%, transparent); color: var(--ls-warn); }
        .ls-card h3 { margin: 12px 0 4px; font-size: 17px; }
        .ls-card .ls-desc { color: var(--ls-muted); font-size: 13.5px; margin: 0 0 14px; min-height: 40px; }
        .ls-price { display: flex; align-items: baseline; gap: 5px; margin: 4px 0 2px; }
        .ls-price .ls-amount { font-size: 34px; font-weight: 800; letter-spacing: -.03em; }
        .ls-price .ls-per { color: var(--ls-muted); font-size: 13.5px; font-weight: 500; }
        .ls-yearly-note { color: var(--ls-muted); font-size: 12px; margin-bottom: 12px; }
        .ls-trial { color: var(--ls-muted); font-size: 12.5px; margin-bottom: 12px; }
        .ls-features { list-style: none; margin: 12px 0 20px; padding: 0; flex: 1; }
        .ls-features li {
            display: flex; gap: 8px; align-items: flex-start;
            font-size: 13.5px; padding: 5px 0; color: var(--ls-text);
        }
        .ls-features li::before {
            content: "✓"; color: var(--ls-accent); font-weight: 800; flex: none;
        }
        .ls-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 100%; padding: 10px 16px; border-radius: 10px; border: 1px solid transparent;
            font-size: 14px; font-weight: 650; cursor: pointer; text-decoration: none;
            background: var(--ls-accent); color: #fff;
            transition: filter .12s ease;
        }
        .ls-btn:hover { filter: brightness(1.06); }
        .ls-btn.is-ghost {
            background: transparent; color: var(--ls-text);
            border-color: var(--ls-border);
        }
        .ls-btn.is-ghost:hover { border-color: var(--ls-accent); color: var(--ls-accent); filter: none; }
        .ls-btn[disabled] { opacity: .55; cursor: default; pointer-events: none; }
        .ls-btn.is-auto { width: auto; }
        .ls-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .ls-section {
            background: var(--ls-card); border: 1px solid var(--ls-border);
            border-radius: var(--ls-radius); box-shadow: var(--ls-shadow);
            padding: 22px; margin-bottom: 18px;
        }
        .ls-section h2 { margin: 0 0 4px; font-size: 17px; }
        .ls-section .ls-sub { color: var(--ls-muted); margin: 0 0 16px; font-size: 13.5px; }
        .ls-kv { display: grid; grid-template-columns: max-content 1fr; gap: 8px 22px; margin: 10px 0 18px; }
        .ls-kv dt { color: var(--ls-muted); font-size: 13.5px; }
        .ls-kv dd { margin: 0; font-weight: 600; font-size: 13.5px; }
        table.ls-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        .ls-table th {
            text-align: start; color: var(--ls-muted); font-weight: 600;
            padding: 8px 10px; border-bottom: 1px solid var(--ls-border);
        }
        .ls-table td { padding: 10px; border-bottom: 1px solid var(--ls-border); }
        .ls-table tr:last-child td { border-bottom: 0; }
        .ls-table a { color: var(--ls-accent); font-weight: 600; text-decoration: none; }
        .ls-muted { color: var(--ls-muted); }
    </style>
</head>
<body>
<div class="ls-shell">
    <header class="ls-header">
        @if (! empty($nav))
            <nav class="ls-nav">
                <a href="{{ $nav['pricing'] ?? '#' }}" class="{{ request()->routeIs('larasaas.embed.pricing') ? 'is-active' : '' }}">{{ __('larasaas::larasaas.pricing.title') }}</a>
                @if (! empty($nav['billing']))
                <a href="{{ $nav['billing'] ?? '#' }}" class="{{ request()->routeIs('larasaas.embed.billing') ? 'is-active' : '' }}">{{ __('larasaas::larasaas.billing.title') }}</a>
                @endif
            </nav>
        @endif
    </header>

    @if (! empty($notice))
        <div class="ls-notice {{ ($notice_tone ?? 'ok') === 'warn' ? 'is-warn' : '' }}">{{ $notice }}</div>
    @endif

    <main>
        @yield('content')
    </main>
</div>
</body>
</html>
