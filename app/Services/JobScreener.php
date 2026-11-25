<?php

namespace App\Services;

use App\Enums\AiVerdict;
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
            maxTokens: 500,
        );

        $verdict = AiVerdict::tryFrom($result['verdict'] ?? '') ?? AiVerdict::Irrelevant;
        $score = max(0, min(100, (int) ($result['score'] ?? 0)));

        $job->fill([
            'ai_verdict' => $verdict,
            'ai_score' => $verdict === AiVerdict::Match ? $score : 0,
            'ai_reason' => $result['reason'] ?? null,
            'ai_scored_at' => now(),
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
            - agency: the poster is a recruiting agency, staffing firm, outsourcing marketplace,
              or talent network hiring on behalf of an unnamed client, rather than the employer itself.
            - language: the role requires a spoken language the candidate doesn't speak.
            - location: the candidate can't take it from where they live (e.g. "US only",
              requires US work authorization, on-site outside Belgrade).
            - irrelevant: not a development role, or the stack doesn't overlap with
              WordPress/PHP/Laravel in a meaningful way.
            - match: none of the above.

            For a match, score 0–100 on fit: stack overlap (WordPress weighs most), seniority,
            and whether stated pay plausibly meets the candidate's floor. If pay isn't stated,
            don't penalise heavily. 80+ means "apply today". Keep the reason to one or two
            short sentences, naming the deciding factor.
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
                ],
                'required' => ['verdict', 'score', 'reason'],
            ],
        ];
    }
}
