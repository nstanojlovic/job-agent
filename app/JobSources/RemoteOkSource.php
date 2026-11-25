<?php

namespace App\JobSources;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * RemoteOK JSON API. The first element of the response is a legal notice,
 * not a job. They ask that you link back to the job URL, which we do.
 */
class RemoteOkSource implements JobSource
{
    public function name(): string
    {
        return 'remoteok';
    }

    public function fetch(): Collection
    {
        $jobs = Http::timeout(30)
            ->withUserAgent(config('jobagent.user_agent'))
            ->get('https://remoteok.com/api')
            ->throw()
            ->json();

        return collect($jobs)
            ->filter(fn ($job) => is_array($job) && isset($job['id'], $job['position']))
            ->map(fn (array $job) => new JobData(
                source: $this->name(),
                externalId: (string) $job['id'],
                url: $job['url'] ?? "https://remoteok.com/remote-jobs/{$job['id']}",
                title: $job['position'],
                company: $job['company'] ?? null,
                location: ($job['location'] ?? '') ?: null,
                salary: $this->salary($job),
                tags: $job['tags'] ?? [],
                description: $job['description'] ?? null,
                postedAt: isset($job['date']) ? CarbonImmutable::parse($job['date']) : null,
            ))
            ->values();
    }

    private function salary(array $job): ?string
    {
        $min = (int) ($job['salary_min'] ?? 0);
        $max = (int) ($job['salary_max'] ?? 0);

        return $min || $max ? '$'.number_format($min).' – $'.number_format($max).' / year' : null;
    }
}
