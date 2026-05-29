<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::statement('ALTER TABLE projects MODIFY image TEXT NULL'),
            'pgsql' => DB::statement('ALTER TABLE projects ALTER COLUMN image TYPE TEXT'),
            default => null,
        };
    }

    public function down(): void
    {
        match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::statement('ALTER TABLE projects MODIFY image VARCHAR(255) NULL'),
            'pgsql' => DB::statement('ALTER TABLE projects ALTER COLUMN image TYPE VARCHAR(255)'),
            default => null,
        };
    }
};
