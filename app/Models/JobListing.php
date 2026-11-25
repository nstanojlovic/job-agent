<?php

namespace App\Models;

use App\Enums\AiVerdict;
use App\Enums\JobStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class JobListing extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'posted_at' => 'datetime',
            'ai_scored_at' => 'datetime',
            'applied_at' => 'datetime',
            'status' => JobStatus::class,
            'ai_verdict' => AiVerdict::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (JobListing $job) {
            if ($job->isDirty('status') && $job->status === JobStatus::Applied && ! $job->applied_at) {
                $job->applied_at = now();
            }
        });
    }

    public function scopeUnscored(Builder $query): Builder
    {
        return $query->whereNull('ai_scored_at');
    }

    /** Plain-text description, trimmed so prompts stay small. */
    public function plainDescription(int $limit = 6000): string
    {
        $text = html_entity_decode(strip_tags((string) $this->description), ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/\s+/u', ' ', $text);

        return mb_strimwidth(trim($text), 0, $limit, '…');
    }
}
