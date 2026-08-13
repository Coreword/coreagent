<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->integer('page_number')->nullable();
            $table->integer('chunk_index')->nullable();
            $table->text('content');
            $table->json('bbox')->nullable();
            // MVP: embedding stored as JSON float array, similarity computed in PHP (EmbeddingService).
            // TODO: migrate to pgvector VECTOR(1536) + HNSW index once Windows build tooling is set up
            // or the app moves to a Linux host where pgvector installs cleanly.
            $table->json('embedding')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chunks');
    }
};
