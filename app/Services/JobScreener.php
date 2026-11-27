<?php

namespace App\Services;

use App\Enums\AiVerdict;
use App\Enums\JobFit;
use App\Enums\JobStatus;
use App\Models\JobListing;
use Illuminate\Support\Facades\File;

/** Asks Claude whether a job fits the candidate, and how well. */
class JobScreener
{
    public function __construct(private readonly Claude $claude) {}

    public function screen(JobListing $job): JobListing
    {
        $result = $this->claude->structured(
            model: config('jobagent.anthropic.screening_model'),
            system: $this->systemPrompt(),
            prompt: $this->jobPrompt($job),
            tool: $this->tool(),
            maxTokens: 800,
        );

        $verdict = AiVerdict::tryFrom($result['verdict'] ?? '') ?? AiVerdict::Irrelevant;
        $score = max(0, min(100, (int) ($result['score'] ?? 0)));
        $isMatch = $verdict === AiVerdict::Match;

        $job->fill([
            'ai_verdict' => $verdict,
            'ai_score' => $isMatch ? $score : 0,
            'ai_reason' => $result['reason'] ?? null,
            'ai_scored_at' => now(),
            'fit' => $isMatch ? JobFit::tryFrom($result['fit'] ?? '') : null,
            'employment_type' => $job->employment_type ?? $this->limited($result['employment_type'] ?? null),
            'notes' => $job->notes ?? ($isMatch ? ($result['notes'] ?? null) : null),
        ]);

        // Hide rejects so the dashboard only shows real candidates.
        // They stay in the database and can be reviewed under the "Hidden" filter.
        if ($verdict !== AiVerdict::Match && $job->status === JobStatus::New) {
            $job->status = JobStatus::Hidden;
        }

        $job->save();

        return $job;
    }

    private function systemPrompt(): string
    {
        $profile = File::get(config('jobagent.profile_path'));

        return <<<PROMPT
            You screen job postings for one job seeker. Be strict and practical.

            <candidate>
            {$profile}
            </candidate>

            Pick exactly one verdict:
            - agency: the poster is a recruiting agency, staffing, outsourcing, outstaffing,
              body-leasing, BPO or staff-augmentation firm, a talent marketplace or "hub", or the
              posting is for an unnamed client ("our client", "we are partnering with"). Judge by
              what the company says it does, not just its name. Web agencies that build sites for
              their own clients with their own team are NOT agencies in this sense.
            - language: the role requires or prefers any spoken language other than English or
              Serbian, including as "nice to have" or "a plus" (Russian, Ukrainian, German, Polish,
              French, Spanish, Portuguese…). iGaming companies often want Russian: check carefully.
            - location: the candidate can't take it from Belgrade, Serbia: limited to the US or to
              any other single country or region that excludes Serbia ("Portugal only", "UK only",
              "Americas/LATAM time zones", requires US work authorization), or on-site/hybrid
              outside Belgrade. Remote roles open to Serbia, Europe, EMEA, CET or worldwide are fine.
            - irrelevant: not a development role, junior or intern level, or the stack doesn't
              overlap with WordPress/PHP/Laravel in a meaningful way.
            - match: none of the above.

            For a match:
            - score 0–100 on fit: stack overlap (WordPress weighs most, then Laravel/PHP, technical
              SEO, web performance, headless WordPress), seniority (medior or senior), and whether
              stated pay plausibly meets the candidate's floor. If pay isn't stated, don't penalise
              heavily. 80+ means "apply today".
            - fit: High = WordPress or Laravel focus at the right level and location; Medium = a
              good role with a gap; Low = a stretch.
            - notes: one or two sentences on why it fits and what to check (stack gaps, rate,
              time zone, whether Serbia is eligible).
            Keep the reason to one or two short sentences, naming the deciding factor.
            PROMPT;
    }

    private function jobPrompt(JobListing $job): string
    {
        $tags = implode(', ', $job->tags ?? []);

        return <<<PROMPT
            Title: {$job->title}
            Company: {$job->company}
            Location: {$job->location}
            Salary: {$job->salary}
            Tags: {$tags}
            Source: {$job->source}

            Description:
            {$job->plainDescription()}
            PROMPT;
    }

    private function tool(): array
    {
        return [
            'name' => 'record_assessment',
            'description' => 'Record the screening result for this job posting.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'verdict' => [
                        'type' => 'string',
                        'enum' => array_column(AiVerdict::cases(), 'value'),
                    ],
                    'score' => [
                        'type' => 'integer',
                        'minimum' => 0,
                        'maximum' => 100,
                        'description' => 'Fit score. Use 0 for anything that is not a match.',
                    ],
                    'reason' => ['type' => 'string'],
                    'fit' => [
                        'type' => 'string',
                        'enum' => array_column(JobFit::cases(), 'value'),
                        'description' => 'Only meaningful for a match.',
                    ],
                    'employment_type' => [
                        'type' => 'string',
                        'description' => 'As stated in the posting, e.g. "Full-time", "B2B contract", "Part-time contract". Use "Not listed" if not stated.',
                    ],
                    'notes' => [
                        'type' => 'string',
                        'description' => 'For a match: 1–2 sentences on why it fits and what to check.',
                    ],
                ],
                'required' => ['verdict', 'score', 'reason', 'fit', 'employment_type', 'notes'],
            ],
        ];
    }

    private function limited(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' || strcasecmp($value, 'Not listed') === 0 ? null : mb_substr($value, 0, 255);
    }
}
