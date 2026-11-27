<?php

namespace App\Services;

use App\Models\JobListing;
use Illuminate\Support\Facades\File;

/** Drafts a cover letter for one job from the candidate's CV. */
class CoverLetterWriter
{
    public function __construct(private readonly Claude $claude) {}

    public function write(JobListing $job): string
    {
        $cv = File::get(config('jobagent.cv_path'));

        $letter = $this->claude->text(
            model: config('jobagent.anthropic.writing_model'),
            system: <<<PROMPT
                You write short, specific cover letters for a developer applying directly to employers.

                <cv>
                {$cv}
                </cv>

                Rules:
                - 180 to 250 words, plain text, no headings, no placeholders like [Company].
                - Open with why this role at this company, not "I am writing to apply".
                - Connect two or three concrete things from the CV to what the posting asks for.
                - Only use facts from the CV. Never invent projects, numbers or skills.
                - Confident, friendly, no clichés ("passionate", "fast-paced", "team player").
                - End with a simple call to action and the candidate's name.
                PROMPT,
            prompt: <<<PROMPT
                Write the cover letter for this job.

                Title: {$job->title}
                Company: {$job->company}
                Location: {$job->location}

                {$job->plainDescription(8000)}

                My private notes on this job (context only, never quote them or mention pay):
                {$job->notes}
                PROMPT,
            maxTokens: 1200,
        );

        $job->update(['cover_letter' => $letter]);

        return $letter;
    }
}
