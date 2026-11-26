@php
    use Illuminate\Support\Carbon;

    $screened = max($stats['screened'], 1);
    $pct = fn (int $n) => round($n / $screened * 100, 2);
    $since = $stats['since'] ? Carbon::parse($stats['since'])->format('j F Y') : null;
    $lastRun = $stats['last_run'] ? Carbon::parse($stats['last_run'])->timezone('Europe/Belgrade') : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#EDF0F3" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#121A23" media="(prefers-color-scheme: dark)">
    <title>job-agent: a job-search agent by Nemanja Stanojlović</title>
    <meta name="description" content="An agent that reads WordPress and PHP job boards every morning, uses Claude to drop agencies and roles I can't take, and scores the rest.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=bricolage-grotesque:400,500,700,800&display=swap" rel="stylesheet">
    <style>
        :root {
            --paper: #EDF0F3;
            --ink: #17212B;
            --slate: #5B6B7A;
            --rule: #C9D2DB;
            --sieve: #3355D1;
            --match: #1E8A5A;
        }

        /* Dark: same sorting-office palette, lights off. Follows the system setting. */
        @media (prefers-color-scheme: dark) {
            :root {
                --paper: #121A23;
                --ink: #E4E9EE;
                --slate: #93A2B1;
                --rule: #2B3744;
                --sieve: #8EA4F7;
                --match: #4CC28A;
            }
        }

        *, *::before, *::after { box-sizing: border-box; }

        html { background: var(--paper); color: var(--ink); }

        body {
            margin: 0;
            font-family: 'Bricolage Grotesque', system-ui, sans-serif;
            font-size: 1.0625rem;
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
        }

        a { color: var(--sieve); text-underline-offset: 0.2em; }
        a:hover { color: var(--ink); }
        a:focus-visible { outline: 3px solid var(--sieve); outline-offset: 3px; border-radius: 2px; }

        .page { max-width: 44rem; margin: 0 auto; padding: 4.5rem 1.5rem 3rem; }

        h1 {
            font-size: clamp(2.4rem, 7vw, 3.6rem);
            font-weight: 800;
            line-height: 1.02;
            letter-spacing: -0.035em;
            margin: 0 0 1.25rem;
            max-width: 18ch;
            text-wrap: balance;
        }

        .lede { font-size: 1.2rem; color: var(--slate); max-width: 34em; margin: 0; }

        h2 {
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: -0.015em;
            margin: 0 0 0.35rem;
        }

        section { margin-top: 4rem; }
        .note { color: var(--slate); margin: 0 0 1.75rem; max-width: 36em; }

        /* The funnel */
        .funnel { list-style: none; margin: 0; padding: 0; }
        .row { padding: 0.8rem 0; border-top: 1px solid var(--rule); }
        .row:last-child { border-bottom: 1px solid var(--rule); }

        .row-head { display: flex; justify-content: space-between; gap: 1rem; align-items: baseline; }
        .row-label { font-weight: 500; }
        .row-count { font-weight: 700; font-variant-numeric: tabular-nums; font-size: 1.15rem; }
        .row-dropped { color: var(--slate); font-size: 0.9rem; font-weight: 400; margin-left: 0.5rem; }

        .bar { height: 0.7rem; margin-top: 0.5rem; background: color-mix(in srgb, var(--rule) 55%, transparent); border-radius: 1px; }
        .bar span {
            display: block; height: 100%;
            min-width: 3px;
            background: var(--sieve);
            border-radius: 1px;
            transform-origin: left;
            animation: fill 0.9s cubic-bezier(.2,.7,.2,1) both;
            animation-delay: calc(var(--i) * 110ms + 150ms);
        }

        .row.is-match .row-label, .row.is-match .row-count { color: var(--match); }
        .row.is-match .bar span { background: var(--match); }
        .row.is-match .row-count { font-size: 1.6rem; }

        @keyframes fill { from { transform: scaleX(0); } to { transform: scaleX(1); } }

        @media (prefers-reduced-motion: reduce) {
            .bar span { animation: none; }
        }

        .meta { color: var(--slate); font-size: 0.9rem; margin-top: 1rem; }

        .empty { padding: 1.25rem 0; border-top: 1px solid var(--rule); border-bottom: 1px solid var(--rule); color: var(--slate); }

        /* How it works: a real sequence, so it's numbered */
        .steps { margin: 0; padding: 0; list-style: none; counter-reset: step; }
        .steps li {
            counter-increment: step;
            display: grid;
            grid-template-columns: 2.25rem 1fr;
            gap: 0.25rem 0.75rem;
            padding: 0.9rem 0;
        }
        .steps li::before {
            content: counter(step);
            font-weight: 800;
            font-size: 1.35rem;
            line-height: 1.2;
            color: var(--sieve);
        }
        .steps strong { display: block; font-weight: 700; }
        .steps p { margin: 0.15rem 0 0; color: var(--slate); grid-column: 2; }

        .stack { margin: 0; color: var(--slate); max-width: 36em; }

        footer {
            margin-top: 4.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--rule);
            display: flex; flex-wrap: wrap; gap: 0.5rem 1.5rem;
            font-size: 0.95rem;
        }
        footer span { color: var(--slate); margin-right: auto; }

        @media (max-width: 30rem) {
            .page { padding-top: 3rem; }
        }
    </style>
</head>
<body>
<main class="page">

    <header>
        <h1>An agent that reads job boards so I don’t have to.</h1>
        <p class="lede">
            Every morning it collects WordPress and PHP jobs from four boards, has Claude throw out
            agencies, language barriers and roles I can’t take from Belgrade, and scores whatever survives.
        </p>
    </header>

    <section aria-labelledby="funnel-title">
        <h2 id="funnel-title">What survived the sieve</h2>
        <p class="note">
            Live numbers{{ $since ? ' since '.$since : '' }}. Each line is one check; the bar shows how much is still left after it.
        </p>

        @if ($stats['screened'] === 0)
            <p class="empty">Nothing screened yet. The first run happens tomorrow at 08:00 Belgrade time.</p>
        @else
            <ol class="funnel">
                <li class="row" style="--i: 0">
                    <div class="row-head">
                        <span class="row-label">Screened by Claude</span>
                        <span class="row-count">{{ number_format($stats['screened']) }}</span>
                    </div>
                    <div class="bar" aria-hidden="true"><span style="width: 100%"></span></div>
                </li>

                @foreach ($stats['steps'] as $i => $step)
                    <li @class(['row', 'is-match' => $loop->last]) style="--i: {{ $i + 1 }}">
                        <div class="row-head">
                            <span class="row-label">{{ $step['label'] }}</span>
                            <span class="row-count">
                                {{ number_format($step['left']) }}<span class="row-dropped">−{{ number_format($step['dropped']) }}</span>
                            </span>
                        </div>
                        <div class="bar" aria-hidden="true"><span style="width: {{ $pct($step['left']) }}%"></span></div>
                    </li>
                @endforeach
            </ol>

            <p class="meta">
                The green line is the shortlist.
                @if ($stats['strong'] > 0)
                    {{ number_format($stats['strong']) }} of those scored 80 or higher, which means apply the same day.
                @endif
                @if ($lastRun)
                    Last run {{ $lastRun->diffForHumans() }}.
                @endif
            </p>
        @endif
    </section>

    <section aria-labelledby="how-title">
        <h2 id="how-title">How it works</h2>
        <ol class="steps">
            <li>
                <strong>Collect</strong>
                <p>A scheduled Laravel command pulls new postings from Remotive, RemoteOK, We Work Remotely and jobs.wordpress.net.</p>
            </li>
            <li>
                <strong>Pre-filter</strong>
                <p>A keyword and age check drops anything that isn’t WordPress or PHP before a single API call is made.</p>
            </li>
            <li>
                <strong>Screen</strong>
                <p>Claude Haiku reads each posting against my profile and answers through a forced tool call, so every verdict is structured: match, agency, language, location or irrelevant, plus a 0–100 fit score and a one-line reason.</p>
            </li>
            <li>
                <strong>Apply</strong>
                <p>Matches land in a private Filament dashboard, sorted by fit. One click drafts a cover letter with Claude Sonnet, grounded only in my CV.</p>
            </li>
        </ol>
    </section>

    <section aria-labelledby="stack-title">
        <h2 id="stack-title">Built with</h2>
        <p class="stack">Laravel 13, Filament 5, MySQL and the Claude API, deployed on Laravel Forge. Every listing links back to the board it came from.</p>
    </section>

    <footer>
        <span>Built by Nemanja Stanojlović, a WordPress and Laravel developer in Belgrade.</span>
        <a href="https://github.com/nstanojlovic/job-agent">Source on GitHub</a>
        <a href="https://anonemus.dev">anonemus.dev</a>
    </footer>

</main>
</body>
</html>
