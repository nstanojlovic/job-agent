<?php

namespace App\Support;

use App\Enums\AiVerdict;
use App\Models\JobListing;
use Illuminate\Support\Facades\Cache;

/**
 * Aggregate, public-safe numbers for the landing page.
 * No titles, companies or application statuses ever leave this class.
 */
class FunnelStats
{
    public static function get(): array
    {
        return Cache::remember('landing.funnel', now()->addMinutes(10), function () {
            $screened = JobListing::whereNotNull('ai_scored_at');

            $byVerdict = (clone $screened)
                ->selectRaw('ai_verdict, count(*) as total')
                ->groupBy('ai_verdict')
                ->pluck('total', 'ai_verdict');

            $count = fn (AiVerdict $v) => (int) ($byVerdict[$v->value] ?? 0);

            $total = (int) $byVerdict->sum();

            // Each step removes one kind of reject; what's left flows on.
            $steps = [];
            $remaining = $total;

            foreach ([
                [AiVerdict::Agency, 'Posted by the employer, not an agency'],
                [AiVerdict::Language, 'No language I don’t speak'],
                [AiVerdict::Location, 'Open to someone in Belgrade'],
                [AiVerdict::Irrelevant, 'A real WordPress / PHP dev role'],
            ] as [$verdict, $label]) {
                $dropped = $count($verdict);
                $remaining -= $dropped;
                $steps[] = ['label' => $label, 'left' => $remaining, 'dropped' => $dropped];
            }

            return [
                'collected' => JobListing::where('added_manually', false)->count(),
                'screened' => $total,
                'steps' => $steps,
                'matches' => $count(AiVerdict::Match),
                'strong' => JobListing::where('ai_score', '>=', 80)->count(),
                'since' => JobListing::where('added_manually', false)->min('created_at'),
                'last_run' => JobListing::max('ai_scored_at'),
            ];
        });
    }
}
