{{-- Shared look for the landing page and the tracker, matching anonemus.dev: dark, Geist + Geist Mono, amber accent. --}}
<meta name="color-scheme" content="dark">
<meta name="theme-color" content="#0e0f12">
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=geist:400,500,600|geist-mono:400,500&display=swap" rel="stylesheet">
<style>
    :root {
        --background: #0e0f12;
        --card: #141619;
        --muted: #1d1f23;
        --border: #27292d;
        --foreground: #eaebee;
        --muted-foreground: #909297;
        --faint: #616368;
        --accent: #f3af49;
        --accent-foreground: #1c150c;
        --accent-soft: rgb(243 175 73 / 0.4);
        --good: #5fc48d;
        --destructive: #e64343;
        --radius: 0.25rem;
        --sans: 'Geist', ui-sans-serif, system-ui, sans-serif;
        --mono: 'Geist Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
        --wrap: 72rem;
    }

    *, *::before, *::after { box-sizing: border-box; }

    html { background: var(--background); color: var(--foreground); }

    body {
        margin: 0;
        font-family: var(--sans);
        font-size: 1rem;
        line-height: 1.5;
        -webkit-font-smoothing: antialiased;
    }

    a { color: inherit; text-decoration: none; }
    a:hover { color: var(--accent); }
    :focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; border-radius: 2px; }
    ::selection { background: var(--accent); color: var(--accent-foreground); }

    .wrap { max-width: var(--wrap); margin: 0 auto; padding: 0 1.5rem; }

    /* Sticky header, same as anonemus.dev */
    .site-header {
        position: sticky; top: 0; z-index: 50;
        border-bottom: 1px solid var(--border);
        background: rgb(14 15 18 / 0.8);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
    }
    .site-header .wrap { display: flex; align-items: center; gap: 1.5rem; height: 3.5rem; }
    .logo { font-family: var(--mono); font-size: 0.875rem; letter-spacing: -0.02em; display: inline-flex; align-items: center; gap: 0.5rem; }
    .logo::before { content: ''; width: 0.45rem; height: 0.45rem; border-radius: 50%; background: var(--accent); }
    .logo span { color: var(--muted-foreground); }
    .site-nav { margin-left: auto; display: flex; gap: 1.5rem; }
    .site-nav a {
        font-family: var(--mono); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em;
        color: var(--muted-foreground);
    }
    .site-nav a:hover, .site-nav a[aria-current="page"] { color: var(--accent); }

    /* Type */
    .eyebrow {
        font-family: var(--mono); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em;
        color: var(--muted-foreground);
        display: inline-flex; align-items: center; gap: 0.6rem; margin: 0;
    }
    .eyebrow::before { content: ''; width: 0.4rem; height: 0.4rem; border-radius: 50%; background: var(--accent); }

    .label {
        font-family: var(--mono); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.1em;
        color: var(--accent); font-weight: 400; margin: 0;
    }

    h1, h2, h3 { font-weight: 600; margin: 0; }

    .display {
        font-size: clamp(2.75rem, 9vw, 6rem);
        line-height: 0.95;
        letter-spacing: -0.025em;
        margin: 1.75rem 0 2rem;
        text-wrap: balance;
    }
    .display .dim { color: var(--muted-foreground); }

    .section-title { font-size: clamp(1.75rem, 4vw, 2.25rem); line-height: 1.1; letter-spacing: -0.025em; margin-top: 0.75rem; }

    .lede { font-size: 1.125rem; line-height: 1.625; color: var(--muted-foreground); max-width: 36rem; margin: 0; }

    .mono { font-family: var(--mono); }
    .muted { color: var(--muted-foreground); }

    /* Buttons and tags */
    .btn {
        display: inline-flex; align-items: center; gap: 0.5rem;
        font-family: var(--mono); font-size: 0.875rem;
        padding: 0.75rem 1.25rem; border-radius: var(--radius);
        border: 1px solid var(--border); color: var(--muted-foreground);
        background: transparent; cursor: pointer;
    }
    .btn:hover { color: var(--foreground); border-color: var(--muted-foreground); }
    .btn-primary { background: var(--accent); border-color: var(--accent); color: var(--accent-foreground); }
    .btn-primary:hover { color: var(--accent-foreground); filter: brightness(1.08); }

    .tag {
        display: inline-block;
        font-family: var(--mono); font-size: 0.625rem; line-height: 1.5;
        text-transform: uppercase; letter-spacing: 0.1em;
        padding: 0.125rem 0.625rem; border-radius: 999px;
        border: 1px solid var(--border); color: var(--muted-foreground);
        white-space: nowrap;
    }
    .tag.accent { border-color: var(--accent-soft); color: var(--accent); }

    /* Footer */
    .site-footer { border-top: 1px solid var(--border); margin-top: 6rem; }
    .site-footer .wrap {
        display: flex; flex-wrap: wrap; gap: 0.5rem 1.5rem; padding-top: 1.75rem; padding-bottom: 2.5rem;
        font-family: var(--mono); font-size: 0.75rem; color: var(--muted-foreground);
    }
    .site-footer .wrap > :first-child { margin-right: auto; }

    @media (max-width: 40rem) {
        .site-nav { gap: 1rem; }
        .site-nav .hide-sm { display: none; }
    }
</style>
