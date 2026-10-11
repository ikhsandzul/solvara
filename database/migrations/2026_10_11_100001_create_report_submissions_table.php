<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_submissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('description');
            $table->foreignId('selected_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('address_text')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('received_at')->useCurrent();
            // status state machine
            $table->string('status')->default('received');
            // temporary file metadata (single primary attachment for v2)
            $table->string('temporary_attachment_path')->nullable();
            $table->string('detected_mime')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('sha256')->nullable();
            $table->decimal('gps_accuracy_m', 8, 2)->nullable();
            // idempotency + lifecycle
            $table->string('idempotency_key')->nullable();
            $table->timestamp('expires_at')->nullable();
            // link to final report once accepted
            $table->string('accepted_report_id')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('expires_at');
            $table->unique('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_submissions');
    }
};
