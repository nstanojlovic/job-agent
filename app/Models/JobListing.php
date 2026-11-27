<?php

namespace App\Models;

use App\Enums\AiVerdict;
use App\Enums\JobFit;
use App\Enums\JobStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class JobListing extends Model
{
    protected $guarded = [];

    /**
     * Human names for the machine source names used by the fetchers.
     *
     * @var array<string, string>
     */
    public const SOURCE_LABELS = [
        'remotive' => 'Remotive',
        'remoteok' => 'RemoteOK',
        'weworkremotely' => 'We Work Remotely',
        'wordpress-jobs' => 'WordPress Jobs',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'posted_at' => 'datetime',
            'found_on' => 'date',
            'apply_by' => 'date',
            'ai_scored_at' => 'datetime',
            'applied_at' => 'datetime',
            'added_manually' => 'boolean',
            'status' => JobStatus::class,
            'fit' => JobFit::class,
            'ai_verdict' => AiVerdict::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (JobListing $job) {
            $job->found_on ??= today();

            // Manually added jobs have no id from a job board.
            $job->external_id ??= 'manual:'.Str::uuid();
        });

        static::saving(function (JobListing $job) {
            if ($job->isDirty('status') && $job->status === JobStatus::Applied && ! $job->applied_at) {
                $job->applied_at = now();
            }

            if ($job->isDirty(['company', 'title'])) {
                $job->dedupe_key = self::dedupeKey($job->company, $job->title);
            }
        });
    }

    /**
     * "SDF Systems GmbH" + "Sr. WordPress Developer" → "sdf systems|senior wordpress developer".
     * Null when the company is unknown, so unrelated "WordPress Developer" posts don't collide.
     */
    public static function dedupeKey(?string $company, ?string $title): ?string
    {
        $company = self::companyKey($company);

        if ($company === null || blank($title)) {
            return null;
        }

        $title = Str::of($title)
            ->lower()
            ->ascii()
            ->replaceMatches('/\(.*?\)|\[.*?\]/', ' ')
            ->replaceMatches('/\bsr\b\.?/', 'senior')
            ->replaceMatches('/\bjr\b\.?/', 'junior')
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();

        return mb_substr($company.'|'.$title, 0, 255);
    }

    /** Company name without legal suffixes or notes, e.g. "Speedline Hub (iGaming client)" → "speedline hub". */
    public static function companyKey(?string $company): ?string
    {
        if (blank($company)) {
            return null;
        }

        $key = Str::of($company)
            ->lower()
            ->ascii()
            ->replaceMatches('/\(.*?\)/', ' ')
            ->replaceMatches('/\b(gmbh|d\.?o\.?o|llc|ltd|inc|uab|s\.?a|b\.?v|ag|sp\. z o\.?o|limited|corp)\b\.?/', ' ')
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();

        return $key === '' ? null : $key;
    }

    /** Jobs from the boards that Claude hasn't looked at yet. Manual and imported jobs are never screened. */
    public function scopeUnscored(Builder $query): Builder
    {
        return $query->whereNull('ai_scored_at')->where('added_manually', false);
    }

    public function sourceLabel(): string
    {
        return self::SOURCE_LABELS[$this->source] ?? $this->source;
    }

    /** Plain-text description, trimmed so prompts stay small. */
    public function plainDescription(int $limit = 6000): string
    {
        $text = html_entity_decode(strip_tags((string) $this->description), ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/\s+/u', ' ', $text);

        return mb_strimwidth(trim($text), 0, $limit, '…');
    }
}
