<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_models', function (Blueprint $table): void {
            $table->boolean('is_transcribe')->default(false)->after('is_image');
        });

        Schema::create('grading_rubrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_user_id')->constrained('chat_users')->cascadeOnDelete();
            $table->string('name');
            $table->text('guidance')->nullable();
            $table->json('criteria');
            $table->timestamps();
        });

        // Deliberately no foreign key to the rubric: a batch keeps its own copy.
        Schema::create('grading_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_user_id')->constrained('chat_users')->cascadeOnDelete();
            $table->string('name');
            $table->json('rubric_snapshot');
            $table->string('model');
            $table->timestamps();
        });

        Schema::create('grading_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grading_batch_id')->constrained('grading_batches')->cascadeOnDelete();
            $table->string('student_name');
            $table->string('type');
            $table->string('original_name');
            $table->string('status');
            $table->json('files')->nullable();
            $table->longText('transcript')->nullable();
            $table->string('transcript_status')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->longText('extracted_text')->nullable();
            $table->json('ai_result')->nullable();
            $table->json('final_scores')->nullable();
            $table->text('lecturer_note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['grading_batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_items');
        Schema::dropIfExists('grading_batches');
        Schema::dropIfExists('grading_rubrics');

        Schema::table('ai_models', function (Blueprint $table): void {
            $table->dropColumn('is_transcribe');
        });
    }
};
