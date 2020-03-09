<?php

namespace App\Providers;

use App\Models\hlyun_oa_process_flow;
use App\Models\hlyun_oa_process_flow_auditing;
use App\Observers\auditingObserver;
use App\Observers\flowObserver;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        'App\Events\oaLogEvent'=>[
            'App\Listeners\oaLogEvent'
        ],
        // 'App\Events\auditingCreated'=>[
        //     'App\Listeners\auditingCreated'
        // ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
        hlyun_oa_process_flow::observe(flowObserver::class);
        hlyun_oa_process_flow_auditing::observe(auditingObserver::class);
        //
    }
}
