<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Source types:
     *   pdf, docx, txt, image — file uploads
     *   audio — audio file (mp3, wav, m4a)
     *   video — video file (mp4, webm) — audio extracted
     *   youtube — YouTube URL
     *   google_drive — Google Drive link
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();

            // source type: pdf|docx|txt|image|audio|video|youtube|google_drive
            $table->string('source_type', 30);

            // original file path in MinIO (null for URL-based sources)
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable()->comment('bytes');

            // For URL-based sources
            $table->string('source_url')->nullable();

            // Processing status: pending|processing|completed|failed
            $table->string('status', 20)->default('pending');

            // Extracted raw text (stored here for quick access, also chunked)
            $table->longText('extracted_text')->nullable();

            // Language detected
            $table->string('language', 10)->nullable()->default('th');

            // Page/duration info
            $table->unsignedInteger('page_count')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable()->comment('For audio/video');

            // Visibility: private|shared|public
            $table->string('visibility', 20)->default('private');

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('user_id');
            $table->index('status');
            $table->index('source_type');
            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
