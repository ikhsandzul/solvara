<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add accepted_from_submission_id to reports for traceability.
        Schema::table('reports', function (Blueprint $table) {
            $table->string('accepted_from_submission_id')->nullable()->unique()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('accepted_from_submission_id');
        });
    }
};
