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
        if (!Schema::hasTable('purpose_work_orders')) {
            Schema::create('purpose_work_orders', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects', 'pn_number')->onDelete('cascade');
            $table->dateTime('wo_date');
            $table->integer('wo_number_in_project');
            $table->string('wo_kode_no');
            $table->foreignId('purpose_id')->nullable()->constrained('purpose_work_orders', 'id');
            $table->text('location')->nullable();
            $table->string('vehicle_no')->nullable();
            $table->string('driver')->nullable();

            // total mandays
            $table->integer('total_mandays_eng')->default(0);
            $table->integer('total_mandays_elect')->default(0);

            // tambahan pekerjaan
            $table->boolean('add_work')->default(false);

            $table->foreignId('approved_by')->nullable()->constrained('users', 'id');
            $table->enum('status', ['waiting approval', 'approved', 'waiting client approval', 'finished']);

            $table->dateTime('start_work_time')->nullable();
            $table->dateTime('stop_work_time')->nullable();

            $table->date('continue_date')->nullable();
            $table->time('continue_time')->nullable();

            $table->text('client_note')->nullable();

            $table->date('scheduled_start_working_date')->nullable();
            $table->date('scheduled_end_working_date')->nullable();

            $table->date('actual_start_working_date')->nullable();
            $table->date('actual_end_working_date')->nullable();

            $table->text('accomodation')->nullable();
            $table->text('material_required')->nullable();

            $table->integer('wo_count')->nullable();

            $table->boolean('client_approved')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users', 'id');

            $table->foreignId('accepted_by')->nullable()->constrained('users', 'id');

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
