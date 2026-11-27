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
    <title>job-agent: a job-search agent by Nemanja Stanojlović</title>
    <meta name="description" content="An agent that reads WordPress and PHP job boards every morning, uses Claude to drop agencies and roles I can't take, and scores the rest.">
    @include('partials.theme')
    <style>
        .hero { padding: 7rem 0 5rem; }
        .hero-grid { display: grid; grid-template-columns: 1fr; gap: 2.5rem; align-items: end; }
        @media (min-width: 56rem) { .hero-grid { grid-template-columns: 1.4fr 1fr; gap: 4rem; } }

        .stats { display: grid; grid-template-columns: 1fr 1fr; border-top: 1px solid var(--border); margin: 0; }
        .stat { padding: 1.25rem 0 0; }
        .stat dt { font-family: var(--mono); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted-foreground); }
        .stat dd { margin: 0.4rem 0 0; font-family: var(--mono); font-size: 1.5rem; font-variant-numeric: tabular-nums; }
        .stat dd.hit { color: var(--accent); }

        .ctas { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 2.5rem; }

        section.block { padding: 5rem 0 0; scroll-margin-top: 4rem; }
        .block-head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: end; gap: 1rem 2rem; margin-bottom: 2.5rem; }
        .block-head .lede { font-size: 1rem; max-width: 30rem; }

        /* Funnel */
        .funnel { list-style: none; margin: 0; padding: 0; border-top: 1px solid var(--border); }
        .row { display: grid; grid-template-columns: 2.5rem 1fr auto; gap: 0.25rem 1rem; align-items: baseline; padding: 1.1rem 0; border-bottom: 1px solid var(--border); }
        .row-num { font-family: var(--mono); font-size: 0.75rem; color: var(--faint); }
        .row-label { font-size: 1.05rem; }
        .row-count { font-family: var(--mono); font-size: 1.05rem; font-variant-numeric: tabular-nums; text-align: right; }
        .row-dropped { color: var(--muted-foreground); font-size: 0.8rem; margin-left: 0.6rem; }
        .bar { grid-column: 2 / -1; height: 0.375rem; margin-top: 0.6rem; background: var(--muted); border-radius: 999px; overflow: hidden; }
        .bar span {
            display: block; height: 100%; min-width: 3px;
            background: var(--faint); border-radius: 999px;
            transform-origin: left;
            animation: fill 0.9s cubic-bezier(.2,.7,.2,1) both;
            animation-delay: calc(var(--i) * 110ms + 150ms);
        }
        .row.is-match .row-label, .row.is-match .row-count { color: var(--accent); }
        .row.is-match .row-count { font-size: 1.5rem; }
        .row.is-match .bar span { background: var(--accent); }

        @keyframes fill { from { transform: scaleX(0); } to { transform: scaleX(1); } }
        @media (prefers-reduced-motion: reduce) { .bar span { animation: none; } }

        .meta { font-family: var(--mono); font-size: 0.8rem; color: var(--muted-foreground); margin: 1.25rem 0 0; line-height: 1.7; }
        .empty { padding: 1.5rem 0; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); color: var(--muted-foreground); margin: 0; }

        /* Steps, like the Experience list on anonemus.dev */
        .steps { list-style: none; margin: 0; padding: 0; border-top: 1px solid var(--border); counter-reset: step; }
        .steps li {
            counter-increment: step;
            display: grid; grid-template-columns: 2.5rem 1fr; gap: 0.35rem 1rem;
            padding: 1.5rem 0; border-bottom: 1px solid var(--border);
        }
        .steps li::before { content: counter(step, decimal-leading-zero); font-family: var(--mono); font-size: 0.75rem; color: var(--faint); padding-top: 0.3rem; }
        .steps h3 { font-size: 1.25rem; letter-spacing: -0.01em; }
        .steps p { grid-column: 2; margin: 0; color: var(--muted-foreground); max-width: 42rem; line-height: 1.65; }

        .tags { display: flex; flex-wrap: wrap; gap: 0.5rem; margin: 0 0 1.25rem; padding: 0; list-style: none; }
    </style>
</head>
<body id="top">

<header class="site-header">
    <div class="wrap">
        <a class="logo" href="#top">jobs<span>.anonemus.dev</span></a>
        <nav class="site-nav" aria-label="Sections">
            <a href="#funnel">Funnel</a>
            <a href="#how">How it works</a>
            <a class="hide-sm" href="#stack">Stack</a>
            <a href="https://anonemus.dev">anonemus.dev ↗</a>
        </nav>
    </div>
</header>

<main class="wrap">

    <section class="hero">
        <p class="eyebrow">Runs every morning at 08:00, Belgrade time</p>
        <h1 class="display">An agent that reads job boards <span class="dim">so I don’t have to.</span></h1>

        <div class="hero-grid">
            <p class="lede">
                Every morning it collects WordPress and PHP jobs from four boards, has Claude throw out
                agencies, language barriers and roles I can’t take from Belgrade, and scores whatever survives.
            </p>

            <dl class="stats">
                <div class="stat">
                    <dt>Screened</dt>
                    <dd>{{ number_format($stats['screened']) }}</dd>
                </div>
                <div class="stat">
                    <dt>Shortlisted</dt>
                    <dd class="hit">{{ number_format($stats['matches']) }}</dd>
                </div>
            </dl>
        </div>

        <div class="ctas">
            <a class="btn btn-primary" href="#funnel">See the funnel ↓</a>
            <a class="btn" href="https://github.com/nstanojlovic/job-agent">Source on GitHub ↗</a>
            <a class="btn" href="https://anonemus.dev">anonemus.dev ↗</a>
        </div>
    </section>

    <section class="block" id="funnel" aria-labelledby="funnel-title">
        <div class="block-head">
            <div>
                <p class="label">// funnel</p>
                <h2 class="section-title" id="funnel-title">What survived the sieve</h2>
            </div>
            <p class="lede">
                Live numbers{{ $since ? ' since '.$since : '' }}. Each line is one check; the bar shows how much is still left after it.
            </p>
        </div>

        @if ($stats['screened'] === 0)
            <p class="empty">Nothing screened yet. The first run happens tomorrow at 08:00 Belgrade time.</p>
        @else
            <ol class="funnel">
                <li class="row" style="--i: 0">
                    <span class="row-num">01</span>
                    <span class="row-label">Screened by Claude</span>
                    <span class="row-count">{{ number_format($stats['screened']) }}</span>
                    <div class="bar" aria-hidden="true"><span style="width: 100%"></span></div>
                </li>

                @foreach ($stats['steps'] as $i => $step)
                    <li @class(['row', 'is-match' => $loop->last]) style="--i: {{ $i + 1 }}">
                        <span class="row-num">{{ str_pad((string) ($i + 2), 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="row-label">{{ $step['label'] }}</span>
                        <span class="row-count">
                            {{ number_format($step['left']) }}<span class="row-dropped">−{{ number_format($step['dropped']) }}</span>
                        </span>
                        <div class="bar" aria-hidden="true"><span style="width: {{ $pct($step['left']) }}%"></span></div>
                    </li>
                @endforeach
            </ol>

            <p class="meta">
                The amber line is the shortlist.
                @if ($stats['strong'] > 0)
                    {{ number_format($stats['strong']) }} of those scored 80 or higher, which means apply the same day.
                @endif
                @if ($lastRun)
                    Last run {{ $lastRun->diffForHumans() }}.
                @endif
            </p>
        @endif
    </section>

    <section class="block" id="how" aria-labelledby="how-title">
        <div class="block-head">
            <div>
                <p class="label">// how it works</p>
                <h2 class="section-title" id="how-title">Four steps, every morning</h2>
            </div>
        </div>

        <ol class="steps">
            <li>
                <h3>Collect</h3>
                <p>A scheduled Laravel command pulls new postings from Remotive, RemoteOK, We Work Remotely and jobs.wordpress.net.</p>
            </li>
            <li>
                <h3>Pre-filter</h3>
                <p>A keyword and age check drops anything that isn’t WordPress or PHP before a single API call is made.</p>
            </li>
            <li>
                <h3>Screen</h3>
                <p>Claude Haiku reads each posting against my profile and answers through a forced tool call, so every verdict is structured: match, agency, language, location or irrelevant, plus a 0–100 fit score and a one-line reason.</p>
            </li>
            <li>
                <h3>Apply</h3>
                <p>Matches land in a private dashboard that doubles as my job tracker: fit, pay, deadline and notes for every role, alongside the ones I find by hand. Jobs I’ve already seen, skipped or been turned down for never come back. One click drafts a cover letter with Claude Sonnet, grounded only in my CV.</p>
            </li>
        </ol>
    </section>

    <section class="block" id="stack" aria-labelledby="stack-title">
        <div class="block-head">
            <div>
                <p class="label">// stack</p>
                <h2 class="section-title" id="stack-title">Built with</h2>
            </div>
        </div>

        <ul class="tags">
            <li class="tag accent">Laravel 13</li>
            <li class="tag accent">Filament 5</li>
            <li class="tag accent">Livewire 4</li>
            <li class="tag">MySQL</li>
            <li class="tag">Claude API</li>
            <li class="tag">Laravel Forge</li>
        </ul>
        <p class="lede">Deployed on Laravel Forge. Every listing links back to the board it came from.</p>
    </section>

</main>

<footer class="site-footer">
    <div class="wrap">
        <span>© {{ now()->year }} Nemanja Stanojlović · WordPress &amp; Laravel developer, Belgrade</span>
        <a href="https://github.com/nstanojlovic/job-agent">GitHub ↗</a>
        <a href="https://anonemus.dev">anonemus.dev ↗</a>
    </div>
</footer>

</body>
</html>
