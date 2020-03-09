<?php

namespace App\Observers;

use App\Models\hlyun_oa_process_flow;
use App\MessageQueue\Facades\MessageQueue;

class flowObserver
{
    /**
     * Handle the hlyun_oa_process_flow "created" event.
     *
     * @param  \App\hlyun_oa_process_flow  $hlyunOaProcessFlow
     * @return void
     */
    public function created(hlyun_oa_process_flow $hlyunOaProcessFlow)
    {
        //
        // dd('created');
    }

    /**
     * Handle the hlyun_oa_process_flow "updated" event.
     *
     * @param  \App\hlyun_oa_process_flow  $hlyunOaProcessFlow
     * @return void
     */
    public function updated(hlyun_oa_process_flow $hlyunOaProcessFlow)
    {
        //进度（1：保存未申请；2：办理中 3：完结通过 4：拒绝失败已结束 5：回退重新提交 6：取消申请已结束
        // url("/process/flowHandleOpen/{$index}");url("/process/flowHandleOpen/{$index}");
        $StepStatus=$hlyunOaProcessFlow->StepStatus;
        if($StepStatus==3){
            $callName="sroa.node.auditingEnd";
            $navigateTitle="点击跳转";
            $navigateUrl=url("/process/flowHandleOpen/{$hlyunOaProcessFlow->ID}");
            MessageQueue::sendMessage($callName,$hlyunOaProcessFlow->ID,compact('navigateTitle','navigateUrl'));

        }

        if($StepStatus==4){
            $callName="sroa.node.auditingRefuse";
            $navigateTitle="点击跳转";
            $navigateUrl=url("/process/flowHandleOpen/{$hlyunOaProcessFlow->ID}");
            MessageQueue::sendMessage($callName,$hlyunOaProcessFlow->ID,compact('navigateTitle','navigateUrl'));
            
        }
        // dd(MessageQueue::sendMessage($callName,$hlyunOaProcessFlow->ID));
        return true;
    }

    /**
     * Handle the hlyun_oa_process_flow "deleted" event.
     *
     * @param  \App\hlyun_oa_process_flow  $hlyunOaProcessFlow
     * @return void
     */
    public function deleted(hlyun_oa_process_flow $hlyunOaProcessFlow)
    {
        //
    }

    /**
     * Handle the hlyun_oa_process_flow "restored" event.
     *
     * @param  \App\hlyun_oa_process_flow  $hlyunOaProcessFlow
     * @return void
     */
    public function restored(hlyun_oa_process_flow $hlyunOaProcessFlow)
    {
        //
    }

    /**
     * Handle the hlyun_oa_process_flow "force deleted" event.
     *
     * @param  \App\hlyun_oa_process_flow  $hlyunOaProcessFlow
     * @return void
     */
    public function forceDeleted(hlyun_oa_process_flow $hlyunOaProcessFlow)
    {
        //
    }
}
