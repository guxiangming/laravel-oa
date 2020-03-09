<?php

namespace App\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\MessageQueue\Facades\MessageQueue;

class auditingCreated
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        $StepStatus=$event->auditing->StepStatus;
        if($StepStatus==1){
            $callName="transaction.node.auditing";
            $navigateTitle="点击跳转";
            $navigateUrl=url("/process/flowHandleOpen/{$event->auditing->FlowId}");
            MessageQueue::sendMessage($callName,$event->auditing->ID,$navigateTitle,$navigateUrl);
        }
        // dd($event);
        //
    }
}
