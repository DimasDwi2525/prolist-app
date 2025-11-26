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
        Schema::create('packing_lists', function (Blueprint $table) {
            $table->string('pl_id')->primary();
            $table->string('pl_number');
            $table->foreignId('pn_id')
                ->nullable()
                ->constrained('projects', 'pn_number')
                ->noActionOnDelete();
            $table->foreignId('destination_id')->nullable()->constrained('destinations');
            $table->foreignId('expedition_id')->nullable()->constrained('master_expeditions');
            $table->dateTime('pl_date')->nullable();
            $table->dateTime('ship_date')->nullable();
            $table->foreignId('pl_type_id')->nullable()->constrained('master_type_packing_lists');
            $table->foreignId('int_pic')->nullable()->constrained('users', 'id');
            $table->string('client_pic')->nullable();
            $table->dateTime('receive_date')->nullable();
            $table->dateTime('pl_return_date')->nullable();
            $table->text('remark')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', 'id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packing_lists');
    }
};
