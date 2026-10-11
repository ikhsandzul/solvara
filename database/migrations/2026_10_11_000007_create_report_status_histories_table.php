<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('old_status');
            $table->string('new_status');
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('report_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_status_histories');
    }
};
