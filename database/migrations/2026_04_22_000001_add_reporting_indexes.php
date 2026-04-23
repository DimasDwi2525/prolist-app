<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->index(['po_date', 'pn_number'], 'projects_po_date_pn_idx');
            $table->index(['target_dates', 'pn_number'], 'projects_target_dates_pn_idx');
            $table->index(['client_id', 'pn_number'], 'projects_client_pn_idx');
            $table->index(['categories_project_id', 'pn_number'], 'projects_category_pn_idx');
            $table->index(['status_project_id', 'pn_number'], 'projects_status_pn_idx');
            $table->index(['project_progress', 'pn_number'], 'projects_progress_pn_idx');
            $table->index('project_number', 'projects_project_number_idx');
            $table->index('project_name', 'projects_project_name_idx');
        });

        Schema::table('phcs', function (Blueprint $table) {
            $table->index(['project_id', 'target_finish_date'], 'phcs_project_target_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['project_id', 'payment_status'], 'invoices_project_payment_idx');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->index('name', 'clients_name_idx');
        });

        Schema::table('project_categories', function (Blueprint $table) {
            $table->index('name', 'project_categories_name_idx');
        });

        Schema::table('status_projects', function (Blueprint $table) {
            $table->index('name', 'status_projects_name_idx');
        });
    }

    public function down(): void
    {
        Schema::table('status_projects', function (Blueprint $table) {
            $table->dropIndex('status_projects_name_idx');
        });

        Schema::table('project_categories', function (Blueprint $table) {
            $table->dropIndex('project_categories_name_idx');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('clients_name_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_project_payment_idx');
        });

        Schema::table('phcs', function (Blueprint $table) {
            $table->dropIndex('phcs_project_target_idx');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('projects_po_date_pn_idx');
            $table->dropIndex('projects_target_dates_pn_idx');
            $table->dropIndex('projects_client_pn_idx');
            $table->dropIndex('projects_category_pn_idx');
            $table->dropIndex('projects_status_pn_idx');
            $table->dropIndex('projects_progress_pn_idx');
            $table->dropIndex('projects_project_number_idx');
            $table->dropIndex('projects_project_name_idx');
        });
    }
};
