<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        // DB::listen(function ($query) {
        //     // Log::info("SQL Executed: " . $query->sql, [
        //     //     'bindings' => $query->bindings,
        //     //     'time' => $query->time
        //     // ]);
        // });

        // Register LogObserver
        \App\Models\Log::observe(\App\Observers\LogObserver::class);

        // Register WorkOrderObserver
        \App\Models\WorkOrder::observe(\App\Observers\WorkOrderObserver::class);

        // Register ApprovalObserver
        \App\Models\Approval::observe(\App\Observers\ApprovalObserver::class);

        // Register RequestInvoiceObserver
        \App\Models\RequestInvoice::observe(\App\Observers\RequestInvoiceObserver::class);
    }
}
