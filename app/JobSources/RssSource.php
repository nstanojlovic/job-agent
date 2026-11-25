<?php

namespace App\JobSources;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

/**
 * Generic RSS source, used for We Work Remotely and jobs.wordpress.net.
 * Each configured feed URL is read and its <item>s become jobs.
 */
class RssSource implements JobSource
{
    /** @param list<string> $feeds */
    public function __construct(
        private readonly string $name,
        private readonly array $feeds,
        private readonly bool $titleHasCompany = false,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function fetch(): Collection
    {
        return collect($this->feeds)
            ->flatMap(fn (string $feed) => $this->items($feed))
            ->unique(fn (JobData $job) => $job->externalId)
            ->values();
    }

    /** @return list<JobData> */
    private function items(string $feed): array
    {
        $body = Http::timeout(30)
            ->withUserAgent(config('jobagent.user_agent'))
            ->get($feed)
            ->throw()
            ->body();

        $xml = @simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NOCDATA);

        if ($xml === false) {
            throw new RuntimeException("Could not parse RSS feed: {$feed}");
        }

        $jobs = [];

        foreach ($xml->channel->item as $item) {
            $link = trim((string) $item->link);
            $title = trim((string) $item->title);
            $company = null;

            // WWR titles look like "Company Name: Job Title"
            if ($this->titleHasCompany && str_contains($title, ': ')) {
                [$company, $title] = explode(': ', $title, 2);
            }

            $tags = [];
            foreach ($item->category as $category) {
                if (trim((string) $category) !== '') {
                    $tags[] = trim((string) $category);
                }
            }

            $guid = trim((string) $item->guid);
            $pubDate = trim((string) $item->pubDate);

            $jobs[] = new JobData(
                source: $this->name,
                externalId: $guid !== '' ? $guid : $link,
                url: $link,
                title: $title,
                company: $company,
                location: trim((string) $item->region) ?: null,
                tags: $tags,
                description: (string) $item->description,
                postedAt: $pubDate !== '' ? CarbonImmutable::parse($pubDate) : null,
            );
        }

        return $jobs;
    }
}
