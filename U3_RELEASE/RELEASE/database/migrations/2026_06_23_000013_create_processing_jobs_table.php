<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks the status of each processing step per document.
     * job_type: ocr | transcribe | embed | summarize
     * status: pending | processing | completed | failed
     */
    public function up(): void
    {
        Schema::create('processing_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();

            // Type of processing step
            $table->string('job_type', 30); // ocr|transcribe|embed|summarize

            // Status
            $table->string('status', 20)->default('pending'); // pending|processing|completed|failed

            // Attempt tracking
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);

            // Error info
            $table->text('error_message')->nullable();
            $table->json('error_context')->nullable();

            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Progress (0-100)
            $table->unsignedTinyInteger('progress')->default(0);

            // Metadata (e.g., OCR confidence score, chunk count)
            $table->json('meta')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('document_id');
            $table->index(['document_id', 'job_type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processing_jobs');
    }
};
