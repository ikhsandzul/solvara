<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->string('flow_version')->nullable();
            $table->string('provider')->nullable();
            $table->string('model_id')->nullable();
            $table->string('category_suggested')->nullable();
            $table->text('summary')->nullable();
            $table->json('keywords_json')->nullable();
            $table->string('urgency_suggestion')->nullable(); // low|medium|high|critical
            $table->string('confidence_band')->nullable(); // low|medium|high
            $table->json('related_report_ids_json')->nullable();
            $table->text('pattern_insight')->nullable();
            $table->text('recommended_next_step')->nullable();
            $table->json('missing_information_json')->nullable();
            $table->boolean('needs_human_review')->default(false);
            $table->string('status')->default('queued'); // queued|processing|completed|failed
            $table->string('error_code')->nullable();
            $table->timestamps();

            $table->index('report_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_analyses');
    }
};
