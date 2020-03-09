<?php

namespace App\Observers;

use App\Models\hlyun_oa_process_flow_auditing;
use App\MessageQueue\Facades\MessageQueue;

class auditingObserver
{
    /**
     * Handle the hlyun_oa_process_flow_auditing "created" event.
     *
     * @param  \App\App\Models\hlyun_oa_process_flow_auditing  $hlyunOaProcessFlowAuditing
     * @return void
     */

    public function creating(hlyun_oa_process_flow_auditing $hlyunOaProcessFlowAuditing)
    {
        // dd('skt');
    }

    

    public function saved(hlyun_oa_process_flow_auditing $hlyunOaProcessFlowAuditing)
    {
        
        // dd($hlyunOaProcessFlowAuditing->fireModelEvent('created'));
        $StepStatus=$hlyunOaProcessFlowAuditing->StepStatus;
        if($StepStatus==1){
            $callName="sroa.node.auditing";
            $navigateTitle="点击跳转";
            $navigateUrl=url("/process/flowHandleOpen/{$hlyunOaProcessFlowAuditing->FlowId}");
            // dd($hlyunOaProcessFlowAuditing->ID);
            MessageQueue::sendMessage($callName,$hlyunOaProcessFlowAuditing->ID,compact('navigateTitle','navigateUrl'));
        }

        // dd(MessageQueue::sendMessage($callName,$hlyunOaProcessFlow->ID));
        return true;
    }


    /**
     * Handle the hlyun_oa_process_flow_auditing "updated" event.
     *
     * @param  \App\App\Models\hlyun_oa_process_flow_auditing  $hlyunOaProcessFlowAuditing
     * @return void
     */
    public function updated(hlyun_oa_process_flow_auditing $hlyunOaProcessFlowAuditing)
    {
       
    }

    /**
     * Handle the hlyun_oa_process_flow_auditing "deleted" event.
     *
     * @param  \App\App\Models\hlyun_oa_process_flow_auditing  $hlyunOaProcessFlowAuditing
     * @return void
     */
    public function deleted(hlyun_oa_process_flow_auditing $hlyunOaProcessFlowAuditing)
    {
        //
    }

    /**
     * Handle the hlyun_oa_process_flow_auditing "restored" event.
     *
     * @param  \App\App\Models\hlyun_oa_process_flow_auditing  $hlyunOaProcessFlowAuditing
     * @return void
     */
    public function restored(hlyun_oa_process_flow_auditing $hlyunOaProcessFlowAuditing)
    {
        //
    }

    /**
     * Handle the hlyun_oa_process_flow_auditing "force deleted" event.
     *
     * @param  \App\App\Models\hlyun_oa_process_flow_auditing  $hlyunOaProcessFlowAuditing
     * @return void
     */
    public function forceDeleted(hlyun_oa_process_flow_auditing $hlyunOaProcessFlowAuditing)
    {
        //
    }
}
