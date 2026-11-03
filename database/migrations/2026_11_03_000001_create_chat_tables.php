<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('pin');
            $table->timestamps();
        });

        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->string('model_id')->unique();
            $table->string('label');
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('supports_effort')->default(false);
            $table->boolean('supports_vision')->default(false);
            $table->boolean('is_image')->default(false);
            $table->timestamps();
        });

        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_user_id')->constrained('chat_users')->cascadeOnDelete();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->string('role');
            $table->longText('content')->nullable();
            $table->string('model')->nullable();
            $table->string('effort')->nullable();
            $table->string('image_path')->nullable();
            $table->json('attachments')->nullable();
            $table->boolean('is_error')->default(false);
            $table->boolean('is_truncated')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
        Schema::dropIfExists('ai_models');
        Schema::dropIfExists('chat_users');
    }
};
