<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_moderation_results', function (Blueprint $table) {
            $table->id();
            $table->string('submission_id');
            $table->foreignId('ai_analysis_run_id')->nullable()->constrained('ai_analysis_runs')->nullOnDelete();
            $table->string('moderation_decision'); // approved|rejected|manual_review
            $table->boolean('is_civic_issue')->default(false);
            $table->string('suggested_category')->nullable();
            $table->text('image_description')->nullable();
            $table->string('description_match')->nullable(); // match|mismatch|uncertain
            $table->string('confidence_band')->nullable(); // low|medium|high
            $table->json('reasons_json')->nullable();
            $table->string('policy_version')->nullable();
            $table->boolean('needs_human_review')->default(false);
            $table->timestamps();

            $table->index('submission_id');
            $table->foreign('submission_id')->references('id')->on('report_submissions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_moderation_results');
    }
};
