<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Job tracker · job-agent</title>
    @include('partials.theme')
    <style>
        .intro { padding: 4.5rem 0 0; }
        .intro h1 { font-size: clamp(2.25rem, 6vw, 3.75rem); line-height: 1; letter-spacing: -0.025em; margin-top: 0.9rem; }

        /* Tabs */
        .tabs { display: flex; flex-wrap: wrap; gap: 0.25rem 1.75rem; margin: 2.5rem 0 0; padding: 0; list-style: none; border-bottom: 1px solid var(--border); }
        .tabs button {
            font-family: var(--mono); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em;
            color: var(--muted-foreground);
            background: none; border: 0; padding: 0.85rem 0; cursor: pointer;
            border-bottom: 2px solid transparent; margin-bottom: -1px;
        }
        .tabs button:hover { color: var(--foreground); }
        .tabs button[aria-current="true"] { color: var(--accent); border-bottom-color: var(--accent); }
        .tabs .count { color: var(--faint); margin-left: 0.4rem; }
        .tabs button[aria-current="true"] .count { color: var(--accent); }

        /* Filters */
        .filters { display: flex; flex-wrap: wrap; gap: 0.75rem; margin: 1.5rem 0 2rem; }
        .field {
            font-family: var(--mono); font-size: 0.8125rem; color: var(--foreground);
            background: var(--card); border: 1px solid var(--border); border-radius: var(--radius);
            padding: 0.6rem 0.85rem;
        }
        .field::placeholder { color: var(--faint); }
        .field:focus { outline: none; border-color: var(--accent); }
        .filters input.field { flex: 1 1 18rem; }

        /* Job list */
        .jobs { margin: 0; padding: 0; list-style: none; display: grid; gap: 0.875rem; }

        .job {
            position: relative;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.25rem 1.5rem 1.25rem 1.75rem;
            transition: border-color 0.2s;
        }
        .job:hover { border-color: #3a3d42; }
        .job::before { content: ''; position: absolute; left: -1px; top: -1px; bottom: -1px; width: 3px; border-radius: var(--radius) 0 0 var(--radius); background: var(--border); }
        .job.fit-High::before { background: var(--accent); }
        .job.fit-Medium::before { background: var(--muted-foreground); }

        .job-head { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem 0.75rem; }
        .job-num { font-family: var(--mono); font-size: 0.75rem; color: var(--faint); }
        .company { font-family: var(--mono); font-size: 0.8125rem; color: var(--muted-foreground); text-transform: uppercase; letter-spacing: 0.06em; }
        .job-head .field { margin-left: auto; padding: 0.35rem 0.6rem; font-size: 0.75rem; }

        .role { font-size: 1.375rem; font-weight: 600; letter-spacing: -0.015em; line-height: 1.25; margin: 0.5rem 0 1.1rem; }

        .facts { display: grid; grid-template-columns: repeat(auto-fill, minmax(10.5rem, 1fr)); gap: 0.9rem 1.5rem; margin: 0; padding-top: 1rem; border-top: 1px solid var(--border); }
        .facts div { min-width: 0; }
        .facts dt { font-family: var(--mono); font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--faint); }
        .facts dd { margin: 0.2rem 0 0; font-size: 0.9375rem; overflow-wrap: anywhere; }
        .facts .none { color: var(--faint); }
        .soon { color: var(--accent); }
        .urgent { color: var(--destructive); }
        .hint { font-family: var(--mono); font-size: 0.75rem; color: var(--muted-foreground); }

        .notes { margin: 1.1rem 0 0; color: var(--muted-foreground); line-height: 1.6; max-width: 62rem; }

        .actions { display: flex; flex-wrap: wrap; gap: 0.6rem; margin-top: 1.25rem; }
        .actions .btn { padding: 0.5rem 0.9rem; font-size: 0.75rem; }

        .empty { padding: 2.5rem 0; color: var(--muted-foreground); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); font-family: var(--mono); font-size: 0.875rem; margin: 0; }

        [wire\:loading].loading-bar { position: fixed; top: 3.5rem; left: 0; right: 0; height: 2px; background: var(--accent); z-index: 60; }

        @media (max-width: 36rem) {
            .job { padding: 1.1rem 1.1rem 1.1rem 1.35rem; }
            .job-head .field { margin-left: 0; width: 100%; }
        }
    </style>
</head>
<body>

<header class="site-header">
    <div class="wrap">
        <a class="logo" href="{{ url('/') }}">jobs<span>.anonemus.dev</span></a>
        <nav class="site-nav" aria-label="Pages">
            <a href="{{ route('tracker') }}" aria-current="page">Tracker</a>
            <a href="{{ url('/admin') }}">Dashboard</a>
            <a class="hide-sm" href="{{ url('/') }}">Public page</a>
        </nav>
    </div>
</header>

<main class="wrap">
    <section class="intro">
        <p class="eyebrow">Private · only you can see this</p>
        <h1>Job tracker</h1>
    </section>

    <livewire:job-tracker />
</main>

<footer class="site-footer">
    <div class="wrap">
        <span>job-agent · tracker</span>
        <a href="{{ url('/admin') }}">Dashboard ↗</a>
    </div>
</footer>

</body>
</html>
