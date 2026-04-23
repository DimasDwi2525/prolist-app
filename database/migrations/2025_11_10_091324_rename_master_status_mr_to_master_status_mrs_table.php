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
        if (!Schema::hasTable('master_status_mr') || Schema::hasTable('master_status_mrs')) {
            return;
        }

        Schema::rename('master_status_mr', 'master_status_mrs');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('master_status_mrs') || Schema::hasTable('master_status_mr')) {
            return;
        }

        Schema::rename('master_status_mrs', 'master_status_mr');
    }
};
