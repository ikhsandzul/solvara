<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_analysis_runs', function (Blueprint $table) {
            $table->id();
            $table->string('submission_id'); // ulid FK, avoid type clash with bigint PK
            $table->string('flow_type'); // image_gate | duplicate_intelligence
            $table->string('flow_id_or_version')->nullable();
            $table->string('provider')->nullable();
            $table->string('model_id')->nullable();
            $table->string('status')->default('queued'); // queued|processing|completed|failed
            $table->unsignedTinyInteger('attempt_number')->default(1);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('response_schema_version')->nullable();
            $table->json('sanitized_output_json')->nullable();
            $table->string('error_code')->nullable();
            $table->timestamps();

            $table->index(['submission_id', 'flow_type']);
            $table->foreign('submission_id')->references('id')->on('report_submissions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_analysis_runs');
    }
};
