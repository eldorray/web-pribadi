<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->string('image_requested_ratio', 12)->nullable()->after('image_path');
            $table->string('image_used_ratio', 12)->nullable()->after('image_requested_ratio');
            $table->boolean('image_fell_back')->default(false)->after('image_used_ratio');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropColumn([
                'image_requested_ratio',
                'image_used_ratio',
                'image_fell_back',
            ]);
        });
    }
};
