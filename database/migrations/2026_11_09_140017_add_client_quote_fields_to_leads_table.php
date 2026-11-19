<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leads')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table): void {
            if (! Schema::hasColumn('leads', 'quoted_price')) {
                $table->decimal('quoted_price', 15, 2)->nullable()->after('target_currency');
            }

            if (! Schema::hasColumn('leads', 'quoted_currency')) {
                $table->string('quoted_currency', 3)->default('RUB')->after('quoted_price');
            }

            if (! Schema::hasColumn('leads', 'quote_status')) {
                $table->string('quote_status', 20)->default('none')->after('quoted_currency');
            }

            if (! Schema::hasColumn('leads', 'quote_sent_at')) {
                $table->timestamp('quote_sent_at')->nullable()->after('quote_status');
            }

            if (! Schema::hasColumn('leads', 'quote_agreed_at')) {
                $table->timestamp('quote_agreed_at')->nullable()->after('quote_sent_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('leads')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table): void {
            foreach (['quote_agreed_at', 'quote_sent_at', 'quote_status', 'quoted_currency', 'quoted_price'] as $column) {
                if (Schema::hasColumn('leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
