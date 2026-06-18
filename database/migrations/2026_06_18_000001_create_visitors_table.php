<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2)->default('XX');
            $table->string('ip_hash', 64);
            $table->string('path')->nullable();
            $table->timestamp('visited_at');
            $table->index('country_code');
            $table->index('visited_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};
