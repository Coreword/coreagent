<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->string('filename', 255)->nullable();
            $table->text('storage_path')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->integer('page_count')->nullable();
            $table->string('doc_type', 100)->nullable();
            $table->decimal('doc_type_conf', 4, 3)->nullable();
            $table->string('ocr_status', 30)->default('pending');
            // Pipeline stage state machine: pending -> ingested -> classified -> extracted -> checked -> summarised
            // (also: failed, awaiting_api_key when an LLM stage is skipped because no API key is configured)
            $table->string('pipeline_status', 30)->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
