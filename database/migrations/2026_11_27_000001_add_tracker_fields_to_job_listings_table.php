<?php

use App\Models\JobListing;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Brings job_listings in line with the hand-kept job tracker:
 * Fit, Type, Found, Apply by, plus a company + role key for deduplication.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            // Tracker rows imported from the spreadsheet may not have a link.
            $table->string('url', 1024)->nullable()->change();

            $table->string('fit')->nullable()->index()->after('ai_reason');
            $table->string('employment_type')->nullable()->after('salary');
            $table->date('found_on')->nullable()->index()->after('posted_at');
            $table->date('apply_by')->nullable()->index()->after('found_on');
            $table->string('dedupe_key')->nullable()->index()->after('external_id');

            // Added by hand or imported from the tracker, never screened by Claude.
            $table->boolean('added_manually')->default(false)->index()->after('source');
        });

        // Backfill existing scraped jobs so they show up correctly in the new columns.
        DB::table('job_listings')->orderBy('id')->each(function (object $row) {
            DB::table('job_listings')->where('id', $row->id)->update([
                'dedupe_key' => JobListing::dedupeKey($row->company, $row->title),
                'found_on' => substr((string) $row->created_at, 0, 10) ?: null,
                'fit' => match (true) {
                    $row->ai_score === null || (int) $row->ai_score === 0 => null,
                    (int) $row->ai_score >= 80 => 'High',
                    (int) $row->ai_score >= 60 => 'Medium',
                    default => 'Low',
                },
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            $table->dropIndex(['fit']);
            $table->dropIndex(['found_on']);
            $table->dropIndex(['apply_by']);
            $table->dropIndex(['dedupe_key']);
            $table->dropIndex(['added_manually']);
            $table->dropColumn(['fit', 'employment_type', 'found_on', 'apply_by', 'dedupe_key', 'added_manually']);
        });
    }
};
