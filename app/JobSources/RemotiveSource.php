<?php

namespace App\JobSources;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Remotive public API. Their terms ask for a link back to the original
 * posting, which we keep in `url` and open from the dashboard.
 */
class RemotiveSource implements JobSource
{
    public function name(): string
    {
        return 'remotive';
    }

    public function fetch(): Collection
    {
        return collect(config('jobagent.search_terms'))
            ->flatMap(fn (string $term) => Http::timeout(30)
                ->get('https://remotive.com/api/remote-jobs', ['search' => $term, 'limit' => 100])
                ->throw()
                ->json('jobs', []))
            ->unique('id')
            ->map(fn (array $job) => new JobData(
                source: $this->name(),
                externalId: (string) $job['id'],
                url: $job['url'],
                title: $job['title'],
                company: $job['company_name'] ?? null,
                location: $job['candidate_required_location'] ?? null,
                salary: ($job['salary'] ?? '') ?: null,
                tags: $job['tags'] ?? [],
                description: $job['description'] ?? null,
                postedAt: isset($job['publication_date']) ? CarbonImmutable::parse($job['publication_date']) : null,
            ))
            ->values();
    }
}
