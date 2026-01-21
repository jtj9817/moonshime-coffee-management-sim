<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create daily_reports table
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('day');
            $table->json('summary_data')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'day']);
        });

        // Add created_day to orders
        Schema::table('orders', function (Blueprint $table) {
            $table->integer('created_day')->nullable()->after('delivery_day');
            $table->index('created_day');
        });

        // Add created_day to alerts
        Schema::table('alerts', function (Blueprint $table) {
            $table->integer('created_day')->nullable()->after('is_resolved');
            $table->index('created_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_reports');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['created_day']);
            $table->dropColumn('created_day');
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->dropIndex(['created_day']);
            $table->dropColumn('created_day');
        });
    }
};
