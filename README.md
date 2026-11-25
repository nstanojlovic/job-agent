# job-agent

A personal job-search agent built with **Laravel**, **Filament** and the **Claude API**.

Every morning it collects new WordPress / PHP jobs from several job boards, uses Claude to screen out what doesn't fit, scores the rest, and lists them in a Filament dashboard where I track applications and draft cover letters.

## How it works

```
jobs:fetch (daily, 08:00 Belgrade time)
   │
   ├─ Sources ──► Remotive API · RemoteOK API · We Work Remotely RSS · jobs.wordpress.net RSS
   │
   ├─ Keyword + age pre-filter (cheap, no AI)
   │
   ├─ Claude Haiku screens each new job against my profile
   │     verdict: match · agency · language · location · irrelevant
   │     score:   0–100 fit for matches, with a one-line reason
   │
   └─ Filament dashboard
         sorted by score · status tracking (new → applied → interview)
         "Draft cover letter" button → Claude Sonnet, grounded in my CV
```

Some design choices:

- **Structured output via forced tool use.** The screener makes Claude answer by calling a `record_assessment` tool, so every result is valid JSON with an enum verdict and a bounded score. No parsing of free text.
- **Two models.** Haiku for high-volume screening (cheap and fast), Sonnet only when I ask for a cover letter.
- **Cheap pre-filter first.** Keyword and age checks drop most postings before any API call.
- **Fail-soft sources.** Each source is a small class behind a `JobSource` interface. If one feed breaks, the others still run. Unscreened jobs are retried on the next run.
- **Private data stays private.** My profile (incl. pay floor) and CV live in git-ignored files.

## Stack

Laravel 13 · Filament 5 · SQLite · Claude API (Haiku + Sonnet) via Laravel's HTTP client · Pest

## Setup

```bash
git clone https://github.com/nstanojlovic/job-agent.git
cd job-agent
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan make:filament-user

cp resources/candidate/profile.example.md resources/candidate/profile.md
cp resources/candidate/cv.example.md resources/candidate/cv.md
```

Add your API key to `.env`:

```
ANTHROPIC_API_KEY=sk-ant-...
```

Run it:

```bash
php artisan jobs:fetch          # collect + screen
php artisan jobs:fetch --no-ai  # collect only
php artisan schedule:work       # run the daily schedule locally
```

Then open `/admin`.

On a server, add Laravel's scheduler cron (`* * * * * php artisan schedule:run`), e.g. via Forge's Scheduler.

## Adding a source

Implement `App\JobSources\JobSource` (a `name()` and a `fetch()` that returns `JobData` objects) and register it in `FetchJobs::sources()`. RSS feeds only need a URL in `config/jobagent.php`.

## Credits

Job data comes from [Remotive](https://remotive.com), [RemoteOK](https://remoteok.com), [We Work Remotely](https://weworkremotely.com) and [jobs.wordpress.net](https://jobs.wordpress.net). Every listing links back to the original posting.
