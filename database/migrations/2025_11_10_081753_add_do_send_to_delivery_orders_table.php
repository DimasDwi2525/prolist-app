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
        if (!Schema::hasTable('delivery_orders') || Schema::hasColumn('delivery_orders', 'do_send')) {
            return;
        }

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->date('do_send')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('delivery_orders') || !Schema::hasColumn('delivery_orders', 'do_send')) {
            return;
        }

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropColumn('do_send');
        });
    }
};
