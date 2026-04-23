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
        $columnsToDrop = array_filter(
            ['payment_date', 'payment_amount'],
            fn (string $column) => Schema::hasColumn('invoices', $column)
        );

        Schema::table('invoices', function (Blueprint $table) {
            //
        });

        if (!empty($columnsToDrop)) {
            Schema::table('invoices', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->decimal('ppn_rate', 5, 2)->nullable();
            $table->decimal('pph23_rate', 5, 2)->nullable();
            $table->decimal('pph42_rate', 5, 2)->nullable();
            $table->decimal('rate_usd', 10, 4)->nullable();
            $table->decimal('nilai_ppn', 18, 2)->nullable();
            $table->decimal('nilai_pph23', 18, 2)->nullable();
            $table->decimal('nilai_pph42', 18, 2)->nullable();
            $table->decimal('total_invoice', 18, 2)->nullable();
            $table->decimal('expected_payment', 18, 2)->nullable();
            $table->date('payment_actual_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('payment_status');
            $table->dropColumn(['ppn_rate', 'pph23_rate', 'pph42_rate', 'rate_usd', 'nilai_ppn', 'nilai_pph23', 'nilai_pph42', 'total_invoice', 'expected_payment', 'payment_actual_date']);
            $table->date('payment_date')->nullable();
            $table->decimal('payment_amount', 18, 2)->nullable();
        });
    }
};
