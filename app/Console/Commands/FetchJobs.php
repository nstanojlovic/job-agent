<?php

namespace App\Console\Commands;

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

            foreach ($jobs as $job) {
                $listing = JobListing::firstOrCreate(
                    ['source' => $job->source, 'external_id' => $job->externalId],
                    $job->toAttributes(),
                );

                $added += $listing->wasRecentlyCreated ? 1 : 0;
            }

            $new += $added;
            $this->info("{$source->name()}: {$jobs->count()} relevant, {$added} new");
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
