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
        Schema::create('request_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('request_number');
            $table->foreignId('project_id')->constrained('projects', 'pn_number')->onDelete('cascade');
            $table->foreignId('requested_by')->constrained('users')->noActionOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('no action');
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_invoices');
    }
};
