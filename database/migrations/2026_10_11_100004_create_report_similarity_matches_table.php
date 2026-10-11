<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_similarity_matches', function (Blueprint $table) {
            $table->id();
            $table->string('submission_id');
            $table->foreignId('candidate_report_id')->constrained('reports')->cascadeOnDelete();
            $table->decimal('distance_m', 10, 2)->nullable();
            $table->string('match_type')->nullable(); // same_issue|recurring_issue|uncertain
            $table->string('confidence_band')->nullable();
            $table->text('reason')->nullable();
            $table->string('review_status')->default('pending'); // pending|confirmed_duplicate|confirmed_different|officer_review
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index('submission_id');
            $table->foreign('submission_id')->references('id')->on('report_submissions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_similarity_matches');
    }
};
