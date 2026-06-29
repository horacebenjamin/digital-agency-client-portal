<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->text('ai_summary')->nullable()->after('completed_at');
            $table->string('ai_summary_status')->default('idle')->after('ai_summary');
            $table->text('ai_summary_error')->nullable()->after('ai_summary_status');
            $table->timestamp('ai_summary_requested_at')->nullable()->after('ai_summary_error');
            $table->timestamp('ai_summary_generated_at')->nullable()->after('ai_summary_requested_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'ai_summary',
                'ai_summary_status',
                'ai_summary_error',
                'ai_summary_requested_at',
                'ai_summary_generated_at',
            ]);
        });
    }
};
