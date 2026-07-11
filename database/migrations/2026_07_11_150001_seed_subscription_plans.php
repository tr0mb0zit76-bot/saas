<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\SubscriptionPlanSeeder',
            '--force' => true,
        ]);
    }

    public function down(): void
    {
        // Plan rows may be edited in platform admin — no rollback.
    }
};
