<?php

namespace App\Models;

use App\Models\Better\Model;
use Illuminate\Support\Facades\DB;
use App\Http\ModuleClass\FlowClass;
use Illuminate\Http\Request;
class hlyun_oa_process_flow extends Model
{

    
    protected $table='hlyun_oa_process_flow';
    protected $guarded = ['ID'];
    protected $casts=[
        'RoleId'=>'array',
        'FormField'=>'array',
        'FormFieldTemp'=>'array',
    ];

    public function hasManyCate(){
        return $this->hasManyThrough(hlyun_oa_process_category::class, hlyun_oa_index_process_category::class, 'ProcessId','ID', 'ProcessId','CategoryId');

    }
    
    public function belongsToProce(){
        return $this->belongsTo(hlyun_oa_process::class,'ProcessId','ID');
    }

    public function hasOneAudit(){
        return $this->hasOne(hlyun_oa_process_flow_auditing::class,'FlowId','ID');
    }

    public function hasAllAudit(){
        return $this->hasManyThrough(hlyun_oa_process_step::class,hlyun_oa_process_flow_auditing::class, 'FlowId','ID', 'ID','StepId')
        ->addSelect('hlyun_oa_process_step.Name',
        'hlyun_oa_process_flow_auditing.StepStatus',
        'hlyun_oa_process_flow_auditing.UserName as AuditingUserName',
        'hlyun_oa_process_flow_auditing.RoleName as AuditingRoleName',
        'hlyun_oa_process_flow_auditing.Task as AuditingTask',
        'hlyun_oa_process_flow_auditing.OrganizationName as AuditingOrganizationName',
        'hlyun_oa_process_flow_auditing.Mind as AuditingMind',
        'hlyun_oa_process_flow_auditing.MindTitle as AuditingMindTitle',
        'hlyun_oa_process_flow_auditing.UpdatedAt',
        'hlyun_oa_process_flow_auditing.ID as AuditingID',
        'hlyun_oa_process_flow_auditing.Feedback');
    }
    //创建api流程
    public static function flowApi($processId,$data){

        \DB::beginTransaction();
        try{
            $model = new static;

            $process=hlyun_oa_process::with(['hasManyForm' => function ($query) {
                $query->where('Status', 1)->select('Name', 'ID', 'Content');
            }])
            ->with([
                'hasManyStep'
            ])
            ->where('ID', $processId)->first()->toArray();
            $model->OANumber=FlowClass::createUniqueNumber();              
            $model->FlowNumber=$process['Name'] . '(' . date('Y-m-d H:i:s') . ':' . oaUser()->ssoInfo('Name') . ')';
            $model->ProcessId=$process['ID'];
            $model->Rank=1;
            $model->Feedback='API创建流程';
            $model->FormField=$data;
            $model->FormFieldTemp=$data;
            // dd($process['hasManyForm'][0]['Content']);
            $model->ApplyTable=$process['has_many_form'][0]['Content'];
            $model->ApplyTableTemp=$process['has_many_form'][0]['Content'];
            //提交类型
            $model->StepStatus=1;
            // $model->StepStatus=$request['StepStatus'];
            //申请用户信息
            // dd(oaUser()->ssoInfo());
            
            
            $model->AccreditId=oaUser()->ssoInfo('AccreditId');
            $model->OrganizationId=oaUser()->ssoInfo('CompanyId');
            $model->OrganizationName=oaUser()->ssoInfo('Company');
            $model->RoleId=implode(',',oaUser()->ssoInfo('RoleIds'));
            $model->RoleName=oaUser()->ssoInfo('Roles');
            $model->Task="";
            $model->UserId=oaUser()->ssoInfo('ID');
            $model->UserName=oaUser()->ssoInfo('Name');
            $model->save();
            //是否创建审核步骤
            //办理进度（1：保存未申请；2：办理中 3：完结通过 4：拒绝失败已结束 5：回退重新提交 6：取消申请已结束;）
            $auditing=[
                'AccreditId'=>$model->AccreditId,
                'ProcessId'=>$model->ProcessId,
                'FlowId'=>$model->ID,
                'Feedback'=>$model->Feedback,
                'OrganizationId'=>$model->OrganizationId,
                'OrganizationName'=>$model->OrganizationName,
                'RoleId'=>$model->RoleId,
                'RoleName'=>$model->RoleName,
                'Task'=>$model->Task,
                'UserId'=>$model->UserId,
                'UserName'=>$model->UserName,
                'StepStatus'=>3
            ];
            //查找步骤1信息
            $stepInfo=hlyun_oa_process_step::StepFirst($model->ProcessId)->first();
            $auditing['StepId']=$stepInfo->ID;
            $FormField=$model->FormField;
            $nodeInfo=hlyun_oa_process_step::select('ID','Name','OrganizationName','RoleName','Task',
            'UserName','Mind','MindTitle','MindId','MindSource')->whereIn('ID',hlyun_oa_process_step::getFlowNextStep($model->ProcessId,$auditing['StepId']))->get()
            ->map(function($item, $key) use($FormField) {
                //格式整理
                $item=$item->toArray();
                $item['MindTitle']=$FormField[$item['MindTitle']]??'';
                if($item['MindSource']=='higher'){
                    if(!isset($FormField['WorkNumber']))throw new OAException("不存在订单号无法追踪所属企业!请联系管理员流程办理人员设定是否合理");
                    $higher=FlowClass::getHigher($FormField['WorkNumber'],$FormField[$item['MindId']]);
                    $item['MindTitle']=$higher['Name'];
                }
                $item['UserName']=FlowClass::getTransactors($item['UserName'],$item['Task'],$item['RoleName'],$item['OrganizationName'],$item['Mind'],$item['MindTitle']);
                return $item;
            })->toArray();
            
            $auditing=hlyun_oa_process_flow_auditing::create($auditing);
            //检查是否存在附件
            if(!empty($data['uploadFile'])){
                $files=self::uploadFiles($data['uploadFile']);
                $files=collect($files)->map(function($item,$k) use($model,$auditing){
                    //记录用户信息
                    $item['UserId']=$model->UserId;
                    $item['UserName']=$model->UserName;
                    $item['AuditingId']=$auditing->ID;
                    $item['CreatedAt']=date("Y-m-d H:i:s");
                    $item['UpdatedAt']=date("Y-m-d H:i:s");
                    return $item;
                })->toArray();
                // dd($files);
                hlyun_oa_process_flow_auditing_files::insert($files);
            
            }
            if(!isset($nodeInfo[0]['ID'])||!isset($nodeInfo[0]['UserName'])){
                return ['code'=>600,'data'=>'流程默认步骤未配置请联系管理员设定'];
            }
            //创建审核二
            request()->offsetSet('StepId', $nodeInfo[0]['ID']??'');
            request()->offsetSet('AuditingId', $auditing->ID);
            request()->offsetSet('Type', 2);
            app('App\Http\Controllers\FlowController')->flowHandleSub();
            \DB::commit();
            return [
            'code'=>0,
            'data'=>'oa',
            'msg'=>"流程待审核！个人中心->协同办公->我的请求,查看流水号为《{$model->FlowNumber}》当前办理人:{$nodeInfo[0]['UserName']}"];
        }catch(\Throwable $t){
            \DB::rollback();
            return ['code'=>600,'data'=>'发起申请失败!联系OA管理员进入调试模式'];
        }
    }
    
    //创建流程
    public static function flow($request)
    {
        \DB::beginTransaction();
        try{
            $ID=$request['FlowId'];
            if($ID){
                $model = static::find($ID);
                //删除审查与文件记录
                $auditingID=hlyun_oa_process_flow_auditing::where('FlowId',$ID)->pluck('ID')->toArray();
                hlyun_oa_process_flow_auditing::whereIn('ID',$auditingID)->delete();
                hlyun_oa_process_flow_auditing_files::whereIn('AuditingID',$auditingID)->delete();
                unset($auditingID);
            }else{           
                $model = new static;
                $model->OANumber=FlowClass::createUniqueNumber();              
            } 
            $model->FlowNumber=$request['FlowNumber'];
            $model->ProcessId=$request['ID'];
            $model->Rank=$request['Rank'];
            $model->Feedback=$request['Feedback'];
            $model->FormField=$request['FormField'];
            $model->FormFieldTemp=$request['FormField'];
            $model->ApplyTable=$request['ApplyTable'];
            $model->ApplyTableTemp=$request['ApplyTable'];
            //提交类型
            $model->StepStatus=1;
            // $model->StepStatus=$request['StepStatus'];
            //申请用户信息
            // dd(oaUser()->ssoInfo());
            $model->AccreditId=oaUser()->ssoInfo('AccreditId');
            $model->OrganizationId=oaUser()->ssoInfo('CompanyId');
            $model->OrganizationName=oaUser()->ssoInfo('Company');
            $model->RoleId=implode(',',oaUser()->ssoInfo('RoleIds'));
            $model->RoleName=oaUser()->ssoInfo('Roles');
            // dd(oaUser()->ssoInfo('RoleIds'));
            // $model->Task=oaUser()->ssoInfo('Task');
            $model->Task="";

            $model->UserId=oaUser()->ssoInfo('ID');
            $model->UserName=oaUser()->ssoInfo('Name');
            $model->save();
            //是否创建审核步骤
            //办理进度（1：保存未申请；2：办理中 3：完结通过 4：拒绝失败已结束 5：回退重新提交 6：取消申请已结束;）
            $auditing=[
                'AccreditId'=>$model->AccreditId,
                'ProcessId'=>$model->ProcessId,
                'FlowId'=>$model->ID,
                'Feedback'=>$model->Feedback,
                'OrganizationId'=>$model->OrganizationId,
                'OrganizationName'=>$model->OrganizationName,
                'RoleId'=>$model->RoleId,
                'RoleName'=>$model->RoleName,
                'Task'=>$model->Task,
                'UserId'=>$model->UserId,
                'UserName'=>$model->UserName,
            ];
            //查找步骤1信息
            $stepInfo=hlyun_oa_process_step::StepFirst($model->ProcessId)->first();
            // dd($stepInfo);
            $auditing['StepId']=$stepInfo->ID;    
            // dd();
            if($request['StepStatus']==1){
                //创建审核步骤
                $auditing['StepStatus']=$model->StepStatus; 
                $nodeInfo="save";   
            }else if($request['StepStatus']==2){
                //进入表单数据校验api
                $auditing['StepStatus']=1;     
                //进行下个节点选项
                // dd(hlyun_oa_process_step::getFlowNextStep($model->ProcessId,$auditing['StepId']));
                $FormField=$model->FormField;
                $nodeInfo=hlyun_oa_process_step::select('ID','Name','OrganizationName','RoleName','Task',
                'UserName','Mind','MindTitle','MindId','MindSource')->whereIn('ID',hlyun_oa_process_step::getFlowNextStep($model->ProcessId,$auditing['StepId']))->get()
                ->map(function($item, $key) use($FormField) {
                    //格式整理
                    $item=$item->toArray();
                    $item['MindTitle']=$FormField[$item['MindTitle']]??'';
                    if($item['MindSource']=='higher'){
                        if(!isset($FormField['WorkNumber']))throw new OAException("不存在订单号无法追踪所属企业!请联系流程管理人员查看设定是否合理");
                        $higher=FlowClass::getHigher($FormField['WorkNumber'],$FormField[$item['MindId']]);
                        $item['MindTitle']=$higher['Name'];
                    }
                    $item['UserName']=FlowClass::getTransactors($item['UserName'],$item['Task'],$item['RoleName'],$item['OrganizationName'],$item['Mind'],$item['MindTitle']);
                    return $item;
                })->toArray();
            }
            $auditing=hlyun_oa_process_flow_auditing::create($auditing);
            //检查是否存在附件
            if(!empty($request['uploadFile'])){
                $files=self::uploadFiles($request['uploadFile']);
                $files=collect($files)->map(function($item,$k) use($model,$auditing){
                    //记录用户信息
                    $item['UserId']=$model->UserId;
                    $item['UserName']=$model->UserName;
                    $item['AuditingId']=$auditing->ID;
                    $item['CreatedAt']=date("Y-m-d H:i:s");
                    $item['UpdatedAt']=date("Y-m-d H:i:s");
                    return $item;
                })->toArray();
                // dd($files);
                hlyun_oa_process_flow_auditing_files::insert($files);
            }
            \DB::commit();
            return ['code'=>0,'data'=>'流程内容已保存!','nodeInfo'=>$nodeInfo,'auditingId'=>$auditing->ID,'FlowId'=>$model->ID];
        }catch(\Throwable $t){
            \DB::rollback();
            // dd($t);
            return ['code'=>600,'data'=>'发起申请失败!'.$t->getMessage(),'msg'=>$t->getTraceAsString(),'message'=>$t->getMessage()];
        }
        
    }
    //查询前进节点
    public static function getNextNode($id)
    {
        return hlyun_oa_process_step::select('ID','Name','OrganizationName','RoleName','Task',
                'UserName')->whereIn('ID',explode(',',$id))->get()->toArray();
    }
    //文件上传处理方法
    public static function uploadFiles($files){
        //用户用户ID归类
        $userId=oaUser()->ssoInfo('ID');
        $dir='//files/'.$userId.'/';
        if (!file_exists(storage_path().$dir)) {
            mkdir (storage_path().$dir,0755,true);
        }
        $res=[];
        $data=[];
        foreach($files as $v){
            $info = $v;
            if(empty($info)){
                throw new \Exception(['code' => 600, 'data' => '文件异常']);
            }
            $Extension = $info ->getClientOriginalExtension();
           
            $OriginalName = $info ->getClientOriginalName();
            $size = $info ->getsize();
            //当前上传时间戳+图片名字作为文件名
            if ($size > 1024 * 1024 * 20) {
                throw new \Exception(['code' => 600, 'data' => '大于20M,无法上传！']);
            }

            if (in_array($Extension, ['exe', 'bat','sh'])) {
                throw new \Exception(['code' => 600, 'data' => '文件异常']);
            }
            $filename = $OriginalName;
            $tmpname = $info->getPathName();
            $realname=time().$filename;
            //文件名追加时间戳
            $path=$dir.$realname;
            $res[] = move_uploaded_file($tmpname, storage_path().$path);
            $data[]=[
                'FilePath'=>$path,
                'FileName'=>$filename
            ];
        }
        if (!in_array(false,$res)){
            return $data;
        }else{
            throw new \Exception('文件上传失败,请联系管理员');
        }

        
    }

    //查询方法
    // 办理进度（1：保存未申请；2：办理中 3：完结通过 4：拒绝失败已结束 5：回退重新提交 6：取消申请已结束）
    public function scopeType($query,$value)
    {
        switch($value){
            case 'ing':
                $value=[1,2,5];
            break;
            case 'end':
                $value=[3,4,6];
            break;
        }
        return $query->whereIn('StepStatus',$value);
    }
    public function scopeRank($query,$value)
    {
        return $query->where('Rank',$value);
    }
    public function scopeFlowNumber($query,$value)
    {
        return $query->where('FlowNumber','like',"%$value%");
    }

    public function scopeOANumber($query,$value)
    {
        return $query->where('OANumber','like',"%$value%");
    }

    public function scopeStepStatus($query,$value)
    {
        return $query->where('StepStatus',$value);
    }
    public function scopeCreatedAt($query,$value)
    {
        switch($value){
            case 1:
                $time=time()-3600*24;
            break;
            case 2:
                $time=time()-3600*24*7;
            break;
            case 3:
                $time=time()-3600*24*30;
            break;
        }
        $date=date( "Y-m-d H:i:s",$time);
        return $query->where('CreatedAt','>',$date);
    }
}
