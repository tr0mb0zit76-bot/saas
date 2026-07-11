<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'tenant_id')) {
            return;
        }

        Schema::table('roles', function (Blueprint $table): void {
            if ($this->hasUniqueIndex('roles', 'name')) {
                $table->dropUnique(['name']);
            }

            if (! $this->hasUniqueIndex('roles', 'tenant_id', 'name')) {
                $table->unique(['tenant_id', 'name']);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        Schema::table('roles', function (Blueprint $table): void {
            if ($this->hasUniqueIndex('roles', 'tenant_id', 'name')) {
                $table->dropUnique(['tenant_id', 'name']);
            }

            if (! $this->hasUniqueIndex('roles', 'name')) {
                $table->unique('name');
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
