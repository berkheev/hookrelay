<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · hookrelay</title>
    <style>
        :root {
            --bg: #f6f7f9; --panel: #fff; --text: #111827; --muted: #6b7280; --border: #e5e7eb;
            --accent: #4f46e5; --ok: #059669; --ok-bg: #ecfdf5; --warn: #b45309; --warn-bg: #fffbeb;
            --bad: #dc2626; --bad-bg: #fef2f2; --code: #f3f4f6;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0b0d12; --panel: #141821; --text: #e5e7eb; --muted: #9ca3af; --border: #262b36;
                --accent: #818cf8; --ok: #34d399; --ok-bg: #0f2a22; --warn: #fbbf24; --warn-bg: #2a2110;
                --bad: #f87171; --bad-bg: #2c1515; --code: #1b2030;
            }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 14px/1.5 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }
        code, pre, .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12.5px; }
        header { background: var(--panel); border-bottom: 1px solid var(--border); }
        header .inner { display: flex; align-items: center; gap: 24px; height: 56px; }
        .brand { font-weight: 700; font-size: 16px; color: var(--text); display: flex; align-items: center; gap: 8px; }
        .brand svg { color: var(--accent); }
        nav a { color: var(--muted); margin-right: 16px; }
        nav a.active { color: var(--text); font-weight: 600; }
        .inner { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
        main { padding: 24px 0 48px; }
        h1 { font-size: 20px; margin: 0 0 16px; }
        h2 { font-size: 15px; margin: 24px 0 12px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 24px; }
        .card { background: var(--panel); border: 1px solid var(--border); border-radius: 10px; padding: 14px 16px; }
        .card .label { color: var(--muted); font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        .card .value { font-size: 24px; font-weight: 650; margin-top: 4px; font-variant-numeric: tabular-nums; }
        .panel { background: var(--panel); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--border); vertical-align: top; }
        th { font-size: 12px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: .04em; background: var(--bg); }
        tr:last-child td { border-bottom: 0; }
        .muted { color: var(--muted); }
        .pill { display: inline-flex; align-items: center; gap: 6px; padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .pill::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .pill.delivered { color: var(--ok); background: var(--ok-bg); }
        .pill.pending { color: var(--warn); background: var(--warn-bg); }
        .pill.failed { color: var(--bad); background: var(--bad-bg); }
        .deliveries { display: flex; flex-direction: column; gap: 4px; }
        .tag { display: inline-block; padding: 1px 6px; border-radius: 4px; background: var(--code); font-size: 12px; }
        .filters { display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; }
        .filters a { padding: 4px 10px; border: 1px solid var(--border); border-radius: 999px; color: var(--muted); background: var(--panel); }
        .filters a.active { border-color: var(--accent); color: var(--accent); }
        pre { background: var(--code); padding: 14px; border-radius: 8px; overflow: auto; margin: 0; max-height: 420px; }
        .flash { background: var(--ok-bg); color: var(--ok); border: 1px solid currentColor; border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; }
        button { font: inherit; cursor: pointer; border: 1px solid var(--border); background: var(--panel); color: var(--text); border-radius: 6px; padding: 4px 10px; }
        button:hover { border-color: var(--accent); color: var(--accent); }
        .two { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media (max-width: 860px) { .two { grid-template-columns: 1fr; } }
        .timeline { list-style: none; margin: 0; padding: 0; }
        .timeline li { display: grid; grid-template-columns: 34px 86px 1fr auto; gap: 10px; padding: 6px 0; border-top: 1px dashed var(--border); align-items: baseline; }
        .timeline li:first-child { border-top: 0; }
        .pagination { margin-top: 16px; display: flex; gap: 12px; }
        .ok { color: var(--ok); } .bad { color: var(--bad); }
    </style>
</head>
<body>
<header>
    <div class="inner">
        <a class="brand" href="{{ route('dashboard') }}">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h6l2-5 3 10 2-5h3"/></svg>
            hookrelay
        </a>
        <nav>
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">Events</a>
            <a href="{{ route('health') }}">Health</a>
        </nav>
    </div>
</header>
<main>
    <div class="inner">
        @if (session('status'))
            <div class="flash">{{ session('status') }}</div>
        @endif
        @yield('content')
    </div>
</main>
</body>
</html>
