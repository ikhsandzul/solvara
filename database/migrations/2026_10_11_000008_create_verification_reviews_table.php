<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('decision'); // verified | reopen
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('report_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_reviews');
    }
};
