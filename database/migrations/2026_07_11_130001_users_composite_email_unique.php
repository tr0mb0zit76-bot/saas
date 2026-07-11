<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'tenant_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if ($this->hasUniqueIndex('users', 'email')) {
                $table->dropUnique(['email']);
            }

            if (! $this->hasUniqueIndex('users', 'tenant_id', 'email')) {
                $table->unique(['tenant_id', 'email']);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if ($this->hasUniqueIndex('users', 'tenant_id', 'email')) {
                $table->dropUnique(['tenant_id', 'email']);
            }

            if (! $this->hasUniqueIndex('users', 'email')) {
                $table->unique('email');
            }
        });
    }

    private function hasUniqueIndex(string $table, string ...$columns): bool
    {
        $indexes = Schema::getIndexes($table);

        foreach ($indexes as $index) {
            if (($index['unique'] ?? false) !== true) {
                continue;
            }

            if ($index['columns'] === $columns) {
                return true;
            }
        }

        return false;
    }
};
