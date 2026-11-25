<?php

namespace App\JobSources;

use Carbon\CarbonImmutable;

/** One job posting, normalized across all sources. */
final readonly class JobData
{
    public function __construct(
        public string $source,
        public string $externalId,
        public string $url,
        public string $title,
        public ?string $company = null,
        public ?string $location = null,
        public ?string $salary = null,
        public array $tags = [],
        public ?string $description = null,
        public ?CarbonImmutable $postedAt = null,
    ) {}

    public function toAttributes(): array
    {
        return [
            'url' => $this->url,
            'title' => mb_substr($this->title, 0, 255),
            'company' => $this->company ? mb_substr($this->company, 0, 255) : null,
            'location' => $this->location ? mb_substr($this->location, 0, 255) : null,
            'salary' => $this->salary ? mb_substr($this->salary, 0, 255) : null,
            'tags' => $this->tags,
            'description' => $this->description,
            'posted_at' => $this->postedAt,
        ];
    }
}
