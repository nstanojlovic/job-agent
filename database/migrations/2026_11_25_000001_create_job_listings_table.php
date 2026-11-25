<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_listings', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('external_id');
            $table->string('url', 1024);
            $table->string('title');
            $table->string('company')->nullable();
            $table->string('location')->nullable();
            $table->string('salary')->nullable();
            $table->json('tags')->nullable();
            $table->longText('description')->nullable();
            $table->timestamp('posted_at')->nullable();

            $table->string('status')->default('new')->index();

            // Claude's assessment
            $table->string('ai_verdict')->nullable()->index();
            $table->unsignedTinyInteger('ai_score')->nullable()->index();
            $table->text('ai_reason')->nullable();
            $table->timestamp('ai_scored_at')->nullable();

            $table->longText('cover_letter')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('applied_at')->nullable();

            $table->timestamps();

            $table->unique(['source', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_listings');
    }
};
