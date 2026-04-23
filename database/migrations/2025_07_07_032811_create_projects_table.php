<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->unsignedBigInteger('pn_number');
            $table->string('project_name');
            $table->string('project_number');
            $table->foreignId('categories_project_id')
                ->constrained('project_categories');
            $table->string('quotations_id');
            $table->foreign('quotations_id')
                ->references('quotation_number')
                ->on('quotations')
                ->onDelete('cascade');
            $table->dateTime('phc_dates')->nullable();
            $table->integer('mandays_engineer')->nullable();
            $table->integer('mandays_technician')->nullable();
            $table->dateTime('target_dates')->nullable();
            $table->string('material_status')->nullable();
            $table->dateTime('dokumen_finish_date')->nullable();
            $table->dateTime('engineering_finish_date')->nullable();
            $table->decimal('jumlah_invoice', 15, 2)->nullable();
            $table->foreignId('status_project_id')
                ->constrained('status_projects');
            $table->string('project_progress')->nullable();
            $table->dateTime('po_date')->nullable();
            $table->string('sales_weeks')->nullable();
            $table->string('po_number')->nullable();
            $table->decimal('po_value', 15,2)->nullable();
            $table->boolean('is_confirmation_order')->default(false);
            $table->unsignedBigInteger('parent_pn_number')->nullable();
            $table->timestamps();

            $table->primary('pn_number'); // <- harus tetap ada di sini
        });

        // Tambahkan foreign key parent_pn_number setelah tabel dibuat
        Schema::table('projects', function (Blueprint $table) {
            $table->foreign('parent_pn_number')
                ->references('pn_number')
                ->on('projects')
                ->onDelete('no action'); // ganti dari 'set null'
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
