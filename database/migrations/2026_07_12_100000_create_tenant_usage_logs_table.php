<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_usage_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->date('recorded_on');
            $table->unsignedInteger('users_count')->default(0);
            $table->unsignedInteger('orders_month_count')->default(0);
            $table->unsignedBigInteger('storage_bytes')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'recorded_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_usage_logs');
    }
};
