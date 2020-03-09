<?php

namespace App\Models;

use App\Models\Better\Model;
use Illuminate\Support\Facades\DB;

class hlyun_oa_process_flow_auditing extends Model
{

    protected $table='hlyun_oa_process_flow_auditing';
    protected $guarded = ['ID'];
    public $timestamps=true;
    protected $casts=[
        // 'hlyun_oa_process_flow_auditing.OrganizationId'=>'array',
        // 'hlyun_oa_process_flow_auditing.RoleId'=>'array',
        // 'hlyun_oa_process_flow_auditing.TaskId'=>'array',
        // 'hlyun_oa_process_flow_auditing.UserId'=>'array',
    ];

    protected $dispatchesEvents = [
        // 'created' => \App\Events\auditingCreated::class,
    ];

    public function belongsToFlow()
    {
        return $this->belongsTo(hlyun_oa_process_flow::class,'FlowId','ID');
    }

    public function belongsToStep()
    {
        return $this->belongsTo(hlyun_oa_process_step::class,'StepId','ID');
    }

    public function belongsToProce()
    {
        return $this->belongsTo(hlyun_oa_process::class,'ProcessId','ID');
    }
    
    public function hasManyCate(){
        return $this->hasManyThrough(hlyun_oa_process_category::class, hlyun_oa_index_process_category::class, 'ProcessId','ID', 'ProcessId','CategoryId');
    }
    //获取审核的最新ID
    public static function getLastAuditID($value){
        return static::where('FlowId',$value)->orderBy('ID','desc')->value('ID');
    }

    public function scopeStepStatus($query,$value)
    {
        // 办理进度（1：办理待处理；2:完结通过；3：完结不通过 ；4：回退不通过  ）
        switch($value){
            case 'host':
                $value=[1];
                return $query->whereIn('hlyun_oa_process_flow_auditing.StepStatus',$value)->where(function($query){
                    $UserId=oaUser()->ssoInfo('ID');
                    $Name=oaUser()->ssoInfo('Name');
                    $AccreditId=oaUser()->ssoInfo('AccreditId');
                    $sqlFirst="hlyun_oa_process_flow_auditing.OrganizationId='\"\"'AND hlyun_oa_process_flow_auditing.RoleId='\"\"' AND hlyun_oa_process_flow_auditing.TaskId='\"\"' AND hlyun_oa_process_flow_auditing.MindId='\"\"' AND FIND_IN_SET($UserId,hlyun_oa_process_flow_auditing.UserId)";
                    $sqlSecond="hlyun_oa_process_flow_auditing.RoleId='' AND hlyun_oa_process_flow_auditing.TaskId='' AND hlyun_oa_process_flow_auditing.UserId='' AND hlyun_oa_process_flow_auditing.MindId='' ";
                    $sqlThird="hlyun_oa_process_flow_auditing.OrganizationId='' AND hlyun_oa_process_flow_auditing.TaskId='' AND hlyun_oa_process_flow_auditing.UserId='' AND hlyun_oa_process_flow_auditing.MindId='' ";
                    $sqlFourth="hlyun_oa_process_flow_auditing.OrganizationId='' AND hlyun_oa_process_flow_auditing.RoleId='' AND hlyun_oa_process_flow_auditing.UserId='' AND hlyun_oa_process_flow_auditing.MindId='' ";
                    // FirstSecondThirdFourthFifthSixthSeventhEighthNinthTenth
                    $sqlFifth="hlyun_oa_process_flow_auditing.OrganizationId='' AND hlyun_oa_process_flow_auditing.RoleId='' AND hlyun_oa_process_flow_auditing.UserId='' AND hlyun_oa_process_flow_auditing.TaskId='' AND hlyun_oa_process_flow_auditing.MindId='' ";
                    $sqlSix="hlyun_oa_process_flow_auditing.OrganizationId='' AND hlyun_oa_process_flow_auditing.RoleId='' AND hlyun_oa_process_flow_auditing.UserId='' AND hlyun_oa_process_flow_auditing.TaskId='' AND 
                    ((MindType='ID' AND hlyun_oa_process_flow_auditing.MindId=$UserId ) OR (MindType='Name' AND hlyun_oa_process_flow_auditing.MindId='{$Name}' ) OR
                    (MindType='Accredit' AND hlyun_oa_process_flow_auditing.MindId='{$AccreditId}' ))";
                    $sqlSeventh="hlyun_oa_process_flow_auditing.OrganizationId='' AND hlyun_oa_process_flow_auditing.RoleId='' AND hlyun_oa_process_flow_auditing.UserId=$UserId AND hlyun_oa_process_flow_auditing.TaskId='' AND hlyun_oa_process_flow_auditing.MindId='' ";
                    //指定企业
                    if(!empty(oaUser()->ssoInfo('GroupAccreditIds'))){
                        $sqlSecond.="AND (";
                        foreach(oaUser()->ssoInfo('GroupAccreditIds') as $v){
                            $sqlSecond.="FIND_IN_SET($v,hlyun_oa_process_flow_auditing.OrganizationId) OR ";
                        }
                        $sqlSecond=rtrim($sqlSecond,"OR ");
                        $sqlSecond.=")";
                    }else{
                        $sqlSecond.=" hlyun_oa_process_flow_auditing.OrganizationId='' ";
                    }

                    if(!empty(oaUser()->ssoInfo('RoleIds'))){
                        $sqlThird.="AND (";
                        foreach(oaUser()->ssoInfo('RoleIds') as $v){
                            $sqlThird.=" FIND_IN_SET($v,hlyun_oa_process_flow_auditing.RoleId) OR ";
                        }
                        $sqlThird=rtrim($sqlThird,"OR ");
                        $sqlThird.=")";
                    }else{
                        $sqlThird.=" hlyun_oa_process_flow_auditing.RoleId='' ";
                    }

                    if(!empty(oaUser()->ssoInfo('GroupAccreditIds'))){
                        $sqlFourth.="AND (";
                        foreach(oaUser()->ssoInfo('GroupAccreditIds') as $v){
                            foreach(explode("+",oaUser()->ssoInfo('Task')) as $vv){
                                $TaskId="$v/$vv";
                                $sqlFourth.="FIND_IN_SET('$TaskId',hlyun_oa_process_flow_auditing.TaskId) OR ";
                            }          
                        }
                        $sqlFourth=rtrim($sqlFourth,"OR ");
                        $sqlFourth.=")";
                    }else{
                        $sqlFourth.=" hlyun_oa_process_flow_auditing.TaskId='' ";
                    }
                    $query->OrWhereRaw($sqlFirst)
                    ->OrWhereRaw($sqlSecond)
                    ->OrWhereRaw($sqlThird)
                    ->OrWhereRaw($sqlFourth)
                    ->OrWhereRaw($sqlFifth)
                    ->OrWhereRaw($sqlSix)
                    ->OrWhereRaw($sqlSeventh);
                });
            break;
            case 'handle':
                $value=[2,3,4];
                return $query->whereIn('hlyun_oa_process_flow_auditing.StepStatus',$value)->where('hlyun_oa_process_flow_auditing.UserId', oaUser()->ssoInfo('ID'));
            break;
        }
       
    }

    public function scopeFlowNumber($query,$value)
    {
        $ID=hlyun_oa_process_flow::where('FlowNumber','like',"%$value%")->pluck('ID')->toArray();
        return $query->whereIn('hlyun_oa_process_flow_auditing.FlowId',$ID);
    }

    public function scopeOANumber($query,$value)
    {
        $ID=hlyun_oa_process_flow::where('OANumber','like',"%$value%")->pluck('ID')->toArray();
        return $query->whereIn('hlyun_oa_process_flow_auditing.FlowId',$ID);
    }

    public function scopeRank($query,$value)
    {
        $ID=hlyun_oa_process_flow::where('Rank',$value)->pluck('ID')->toArray();
        return $query->whereIn('ProcessId',$ID);
    }

    public function scopeCreatedAt($query,$value)
    {
        $ID=hlyun_oa_process_flow::where('Rank',$value)->pluck('ID')->toArray();
        return $query->whereIn('ProcessId',$ID);
    }
}
