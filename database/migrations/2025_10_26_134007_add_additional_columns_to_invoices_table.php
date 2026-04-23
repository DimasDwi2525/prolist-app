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
        $columns = [
            'ppn_rate' => fn (Blueprint $table) => $table->decimal('ppn_rate', 8, 4)->nullable(),
            'pph23_rate' => fn (Blueprint $table) => $table->decimal('pph23_rate', 8, 4)->nullable(),
            'pph42_rate' => fn (Blueprint $table) => $table->decimal('pph42_rate', 8, 4)->nullable(),
            'rate_usd' => fn (Blueprint $table) => $table->decimal('rate_usd', 10, 4)->nullable(),
            'nilai_ppn' => fn (Blueprint $table) => $table->decimal('nilai_ppn', 18, 2)->nullable(),
            'nilai_pph23' => fn (Blueprint $table) => $table->decimal('nilai_pph23', 18, 2)->nullable(),
            'nilai_pph42' => fn (Blueprint $table) => $table->decimal('nilai_pph42', 18, 2)->nullable(),
            'total_invoice' => fn (Blueprint $table) => $table->decimal('total_invoice', 18, 2)->nullable(),
            'expected_payment' => fn (Blueprint $table) => $table->decimal('expected_payment', 18, 2)->nullable(),
            'payment_actual_date' => fn (Blueprint $table) => $table->date('payment_actual_date')->nullable(),
        ];

        foreach ($columns as $column => $definition) {
            if (!Schema::hasColumn('invoices', $column)) {
                Schema::table('invoices', $definition);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['ppn_rate', 'pph23_rate', 'pph42_rate', 'rate_usd', 'nilai_ppn', 'nilai_pph23', 'nilai_pph42', 'total_invoice', 'expected_payment', 'payment_actual_date']);
        });
    }
};
