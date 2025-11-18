<?php

namespace App\Providers;

use App\Events\PhcApprovalUpdatedEvent;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        \App\Events\PhcCreatedEvent::class => [
            \App\Listeners\SendPhcValidationNotification::class,
        ],
        \App\Events\RequestInvoiceCreated::class => [
            \App\Listeners\SendRequestInvoiceNotification::class,
        ],
        \App\Events\LogApprovalUpdated::class => [
            \App\Listeners\SendLogApprovalNotification::class,
        ],
        \App\Events\LogCreatedEvent::class => [
            \App\Listeners\SendLogCreatedNotification::class,
        ],
        \App\Events\WorkOrderCreatedEvent::class => [
            \App\Listeners\SendWorkOrderCreatedNotification::class,
        ],
        \App\Events\WorkOrderUpdatedEvent::class => [
            \App\Listeners\SendWorkOrderUpdatedNotification::class,
        ],
    ];


    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
