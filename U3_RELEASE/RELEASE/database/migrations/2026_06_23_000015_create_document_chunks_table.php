<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each chunk is a text segment from a document.
     * Stored here for reference; actual vector stored in ChromaDB.
     * chroma_id links MariaDB record → ChromaDB vector.
     */
    public function up(): void
    {
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();

            // Chunk order within document
            $table->unsignedSmallInteger('chunk_index');

            // The actual text content
            $table->text('content');

            // Token count (for context window management)
            $table->unsignedSmallInteger('token_count')->nullable();

            // Source location
            $table->unsignedSmallInteger('page_number')->nullable();
            $table->unsignedInteger('char_start')->nullable();
            $table->unsignedInteger('char_end')->nullable();

            // ChromaDB vector ID (UUID)
            $table->string('chroma_id', 36)->nullable()->unique();

            // Embedding status
            $table->boolean('is_embedded')->default(false);

            // OCR confidence for this chunk (0.0-1.0)
            $table->float('ocr_confidence')->nullable();

            $table->timestamps();

            // Indexes — these are queried frequently
            $table->index('document_id');
            $table->index(['document_id', 'chunk_index']);
            $table->index('chroma_id');
            $table->index(['document_id', 'is_embedded']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
