<?php

namespace App\Console\Commands;

use App\Enums\JobStatus;
use App\JobSources\JobData;
use App\JobSources\JobSource;
use App\JobSources\RemoteOkSource;
use App\JobSources\RemotiveSource;
use App\JobSources\RssSource;
use App\Models\JobListing;
use App\Services\JobScreener;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class FetchJobs extends Command
{
    protected $signature = 'jobs:fetch
        {--no-ai : Only collect jobs, skip Claude screening}
        {--limit=100 : Max number of jobs to screen in one run}';

    protected $description = 'Collect new WordPress/PHP jobs from all sources and screen them with Claude';

    public function handle(JobScreener $screener): int
    {
        $new = 0;
        $blocked = $this->blockedCompanyKeys();

        foreach ($this->sources() as $source) {
            try {
                $jobs = $source->fetch()->filter(fn (JobData $job) => $this->isRelevant($job));
            } catch (Throwable $e) {
                // One broken feed shouldn't stop the others.
                $this->warn("{$source->name()}: failed ({$e->getMessage()})");
                report($e);

                continue;
            }

            $added = 0;
            $skipped = 0;

            foreach ($jobs as $job) {
                if ($this->shouldSkip($job, $blocked)) {
                    $skipped++;

                    continue;
                }

                $listing = JobListing::firstOrCreate(
                    ['source' => $job->source, 'external_id' => $job->externalId],
                    $job->toAttributes(),
                );

                $added += $listing->wasRecentlyCreated ? 1 : 0;
            }

            $new += $added;
            $this->info("{$source->name()}: {$jobs->count()} relevant, {$added} new, {$skipped} already tracked or blocked");
        }

        $this->info("Total new jobs: {$new}");

        if ($this->option('no-ai')) {
            return self::SUCCESS;
        }

        $this->screen($screener);

        return self::SUCCESS;
    }

    private function screen(JobScreener $screener): void
    {
        $pending = JobListing::unscored()->latest('posted_at')->limit((int) $this->option('limit'))->get();

        if ($pending->isEmpty()) {
            $this->info('Nothing to screen.');

            return;
        }

        $this->info("Screening {$pending->count()} jobs with Claude…");

        $matches = 0;

        $this->withProgressBar($pending, function (JobListing $job) use ($screener, &$matches) {
            try {
                $screened = $screener->screen($job);
                $matches += $screened->ai_score > 0 ? 1 : 0;
            } catch (Throwable $e) {
                // Left unscored, so the next run tries again.
                report($e);
            }
        });

        $this->newLine();
        $this->info("Matches: {$matches}");
    }

    /** @return list<JobSource> */
    private function sources(): array
    {
        $feeds = config('jobagent.feeds');

        return [
            new RemotiveSource,
            new RemoteOkSource,
            new RssSource('weworkremotely', $feeds['weworkremotely'], titleHasCompany: true),
            new RssSource('wordpress-jobs', $feeds['wordpress-jobs']),
        ];
    }

    /**
     * Company keys to drop outright: known agencies from config, plus every company
     * where I already marked a role Skipped or Rejected (e.g. SOFTSWISS wanted Russian).
     *
     * @return array<string, true>
     */
    private function blockedCompanyKeys(): array
    {
        return collect(config('jobagent.blocked_companies', []))
            ->merge(JobListing::whereIn('status', JobStatus::closed())->pluck('company'))
            ->map(fn (?string $company) => JobListing::companyKey($company))
            ->filter()
            ->mapWithKeys(fn (string $key) => [$key => true])
            ->all();
    }

    /**
     * Skip jobs that are already tracked (same link, or same company + role, whatever
     * the status or source) and jobs from blocked companies.
     *
     * @param  array<string, true>  $blocked
     */
    private function shouldSkip(JobData $job, array $blocked): bool
    {
        $companyKey = JobListing::companyKey($job->company);

        if ($companyKey !== null && isset($blocked[$companyKey])) {
            return true;
        }

        $dedupeKey = JobListing::dedupeKey($job->company, $job->title);

        return JobListing::query()
            ->where(function ($query) use ($job, $dedupeKey) {
                $query->where('url', $job->url);

                if ($dedupeKey !== null) {
                    $query->orWhere('dedupe_key', $dedupeKey);
                }
            })
            ->exists();
    }

    /** Cheap keyword + age filter, so Claude only sees plausible jobs. */
    private function isRelevant(JobData $job): bool
    {
        if ($job->postedAt && $job->postedAt->lt(now()->subDays(config('jobagent.max_age_days')))) {
            return false;
        }

        // jobs.wordpress.net is WordPress-only by definition.
        if ($job->source === 'wordpress-jobs') {
            return true;
        }

        $haystack = Str::lower($job->title.' '.implode(' ', $job->tags).' ');

        return Str::contains($haystack, config('jobagent.keywords'))
            || Str::contains(Str::lower((string) $job->description), 'wordpress');
    }
}
