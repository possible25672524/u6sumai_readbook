<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->unique()->constrained()->cascadeOnDelete();

            // Full transcription text
            $table->longText('content');

            // Whisper-returned language
            $table->string('language', 10)->nullable();

            // Confidence / duration
            $table->float('avg_logprob')->nullable()->comment('Whisper average log probability');
            $table->unsignedInteger('duration_seconds')->nullable();

            // JSON segments: [{start, end, text}]
            $table->json('segments')->nullable();

            // Provider used: whisper
            $table->string('provider', 30)->default('whisper');
            $table->string('model', 60)->nullable()->default('whisper-1');

            $table->timestamps();

            $table->index('document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcripts');
    }
};
