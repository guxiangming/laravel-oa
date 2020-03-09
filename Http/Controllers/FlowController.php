<?php

namespace App\Http\Controllers;

use App\Events\oaLogEvent;
use App\Http\ModuleClass\HelperClass;
use function GuzzleHttp\json_encode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use QL\QueryList;
use Validator;
use App\Rules\checkNameUnique;
use App\Models\hlyun_oa_process_category;
use App\Models\hlyun_oa_form;
use App\Models\hlyun_oa_process;
use App\Models\hlyun_oa_process_flow;
use App\Models\hlyun_oa_process_step;
use App\Models\hlyun_oa_process_flow_auditing;
use App\Models\hlyun_oa_process_flow_auditing_files;
use App\Http\ModuleClass\FlowClass;
use App\Exceptions\OAException;
use App\Models\hlyun_oa_api;
use App\Http\ModuleClass\OrderClass;
use App\Models\Sso\hlyun_sso_organizations;
use App\MessageQueue\Facades\MessageQueue;
use App\Models\hlyun_oa_link;
use App\Models\hlyun_oa_log;
use App\Models\Sso\hlyun_sso_index_position_user;
use App\Models\Sso\hlyun_sso_position;
use App\Models\Sso\hlyun_sso_users;

use App\Models\Oms\hlyun_order_pay_costs;
/**
 * @Description: oa申请 审核 返回大写参数的话是需要前端传递回来
 * @Param: 
 * @Author: czm
 * @return: 
 * @Date: 2019-07-17 18:05:07
 */
class FlowController extends Controller
{

    public function test(){
        // dd(request()->server('SERVER_NAME'));

         
    }
    public function flowIndex(Request $request)
    {
    
        $sql = hlyun_oa_process::where('ID', '!=', '')->where('Status', 1)->Nochlid()->Role();
        // var_dump(($request['Status']));exit;
        $data = $sql->with(['hasManyCate' => function ($query) {
            $query->where('Status', 1)->select('Name');
        }])->get()
        ->map(function ($item, $key) {
            $item = $item->toArray();
            $item['CategoryName'] = $item['has_many_cate'][0]['Name'];
            unset($item['has_many_cate']);
            return $item;
        })->groupBy('CategoryName')->toArray();
        return view('flow.flowIndex', ['data' => $data]);
    }

    public function flowApplyOpen(Request $request)
    {
        $data = hlyun_oa_process::with(['hasManyForm' => function ($query) {
            $query->where('Status', 1)->select('Name', 'ID', 'Content');
        }])
            ->with([
                'hasManyStep'
            ])
            ->where('ID', $request['ID'])->first()->toArray();
        $step = app('\App\Models\hlyun_oa_process_step')->getFlowStep($data['has_many_step']);

        $processStep = [];
        foreach ($step as $k => $v) {
            $processStep[] = hlyun_oa_process_step::select('ID', 'Name', 'OrganizationName', 'RoleName', 'Task', 'UserName', 'Mind')->whereIn('ID', $v)
                ->orderByRaw(DB::raw("FIND_IN_SET(ID, '" . implode(',', $v) . "'" . ')'))
                ->get()->map(function ($item, $key) {
                    $item = $item->toArray();
                    if (!$item['OrganizationName'] && !$item['RoleName'] && !$item['Task'] && !$item['UserName'] && !$item['Mind']) {
                        $item['Mind'] = "不限制";
                    }
                    return $item;
                })->toArray();
        }

        $data['FormId'] = $data['has_many_form'][0]['ID'];
        $data['FormName'] = $data['has_many_form'][0]['Name'];
        $data['Content'] = $data['has_many_form'][0]['Content'];
        $stepId = hlyun_oa_process_step::StepFirst($data['ID'])->value('ID');
        
        //初始化步骤数据
        $stepData = FlowClass::getStepData($stepId);
        
        // return $stepData;
        //事务标题加用户名
        $data['Title'] = $data['Name'] . '(' . date('Y-m-d H:i:s') . ':' . oaUser()->ssoInfo('Name') . ')';
        unset($data['has_many_form']);
        unset($data['has_many_step']);
        // HelperClass::errorlog('data',json_encode($data));

        //步骤Id
        return ['code' => 0, 'data' => $data, 'processStep' => $processStep, 'stepData' => $stepData, 'StepId' => $stepId];
    }

    //外部Api请求表单生成的审核表格
    public function apiAuditing(Request $request)
    {
        $request = $request->all();
        $validator = \Validator::make($request, [
            'processId' => 'required',
            'data' => 'required',
            'sso_token' => 'required',
        ], [
            'processId.required' => '流程Id不能为空',
            'data.required' => '表单数据不能为空',
            'sso_token.required' => 'sso数据不能为空',
        ]);
        if ($validator->fails()) {
            $error = $validator->errors()->all();
            return ['code' => 600, 'data' => $error];
        }
        //判断是否只是获取链接而已
        if (isset($request['index'])) {
            $link = hlyun_oa_link::where('Index', $request['index'])->first();
            return ['code' => 0, 'data' => $request['url'] . $link['Route'] . '?sso_token=' . $request['sso_token'] . '&type=' . $link['ProcessId']];
        }

        //权限身份检验
        $ID = hlyun_oa_process_step::StepFirst($request['processId'])->value('ID');
        if (FlowClass::checkFlowStepPermission($ID,'')) {
            // return oaUser()->ssoInfo('ID');
            if(isset($request['applyBatch'])&&$request['applyBatch']==true){
                foreach($request['data'] as $v){
                    hlyun_oa_process_flow::flowApi($request['processId'],$v);
                }
                return ['code' => 0, 'data' => '流程待审核查看《个人中心->协同办公->我的请求》'];
            }else{
                return hlyun_oa_process_flow::flowApi($request['processId'],$request['data']);
            }
        } else {
            return ['code' => 600, 'data' => '该帐号与流程定义权限不匹配!'];
        }
    }
    //用户审核申请提交
    public function flowApplySub(Request $request)
    {
        
        // dd($request->all());
        $request = $request->all();
        $validator = \Validator::make($request, [
            'ID' => 'required',
            'FlowNumber' => ['required'],
        ], [
            'FlowNumber.required'    => '流程流水不能为空',
            'ID.required'    => '流程ID不能为空',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors()->all()[0];
            return response()->json(['code' => 600, 'data' => $errors]);
        }

        //权限身份检验
        $ID = hlyun_oa_process_step::StepFirst($request['ID'])->value('ID');
        if (FlowClass::checkFlowStepPermission($ID, '')) {
            parse_str($request['ApplyTableData'], $request['FormField']);
            // $request['FormField'] = FlowClass::getQueryData($request['ApplyTable']);
            // dd($request);
            return hlyun_oa_process_flow::flow($request);
        } else {
            return ['code' => 600, 'data' => '该帐号与流程定义权限不匹配!'];
        }
    }



    //后端流程列表

    public function flowAllIndex()
    {
        return view('flow.flowAllIndex');
    }

    public function flowAllList(Request $request)
    {

        $sql = hlyun_oa_process_flow::select('FlowNumber', 'CreatedAt', 'StepStatus', 'Rank', 'ID', 'ProcessId', 'OrganizationName', 'UserName')
            ->with(['hasManyCate' => function ($query) {
                $query->select('Name');
            }])
            ->with(['belongsToProce' => function ($query) {
                $query->select('Name', 'ID');
            }])
            // ->with(['hasOneAudit'=>function($query){
            //     $query->select('ID','UserName','StepStatus','FlowId','Sort','StepId')->orderBy('ID','desc');
            // }])
            ->with('hasAllAudit');
        $request = $request->all();
        $page = $this->setPage($request);
        if (isset($request['FlowNumber']) && $request['FlowNumber'] != null) {
            $sql = $sql->FlowNumber($request['FlowNumber']);
        }
        if (isset($request['CreatedAt']) && $request['CreatedAt'] != null) {
            $sql = $sql->CreatedAt($request['CreatedAt']);
        }
        if (isset($request['Rank']) && $request['Rank'] != null) {
            $sql = $sql->Rank($request['Rank']);
        }
        $data = $sql->skip($page['skip'])->limit($page['pageSize'])->orderBy('ID', 'desc')->get()->map(function ($item, $key) {
            //格式整理
            $item = $item->toArray();
            $item['ApplyOrganizationName'] = $item['OrganizationName'];
            $item['ApplyUser'] = $item['UserName'];
            $item['CateName'] = $item['has_many_cate'][0]['Name'];
            $item['ProceName'] = $item['belongs_to_proce']['Name'];
            $item['FlowStepStatus'] = FlowClass::getFlowStepStatus($item['StepStatus'])->param;
            $item['Rank'] = FlowClass::getRank($item['Rank']);
            //办理还是 查看
            $item['FlowStatus'] = FlowClass::getFlowStepStatus($item['StepStatus'])->status;
            //计算第几部
            $item['AuditSort'] = count($item['has_all_audit']);
            $item['has_all_audit'] = end($item['has_all_audit']);
            // dd($item);
            $item['AuditUserName'] = FlowClass::getTransactors(
                $item['has_all_audit']['AuditingUserName'],
                $item['has_all_audit']['AuditingTask'],
                $item['has_all_audit']['AuditingRoleName'],
                $item['has_all_audit']['AuditingOrganizationName'],
                $item['has_all_audit']['AuditingMind'],
                $item['has_all_audit']['AuditingMindTitle']
            );
            // $item['has_all_audit']['AuditingUserName'];
            $item['AuditStepStatus'] = FlowClass::getAuditingStepStatus($item['has_all_audit']['StepStatus']);
            $item['AuditName'] = $item['has_all_audit']['Name'];
            $item['AuditTime'] = $item['has_all_audit']['UpdatedAt'];
            unset($item['has_many_cate']);
            unset($item['has_all_audit']);
            unset($item['belongs_to_proce']);
            return $item;
        })->toArray();
        $count = $sql->count();
        $pageCount = ceil($count / $page['pageSize']);
        if ($count == 0) {
            $startCount = $page['skip'];
        } else {
            $startCount = $page['skip'] + 1;
        }
        $endCount = $page['skip'] + count($data);
        return ['code' => 0, 'data' => $data, 'pageCount' => $pageCount, 'count' => $count, 'startCount' => $startCount, 'endCount' => $endCount];
    }

    //我的请求
    public function ownflow()
    {

        $module=261;
        $userSearchFields = FlowClass::getSearchFields([$module]);
        $fieldList = $userSearchFields[$module];
        // dd($fieldList);
        // $module=1;
        return view('flow.ownflow', ['fieldList' => $fieldList,'module'=>$module]);
    }
     /**
     *  保存搜索自定义
     * @Author   Allen
     * @DateTime 2019-12-02
     * @return   [type]     [description]
     */
    public function saveSearchFields(){
        $request=\Request::all();
        $validator = \Validator::make($request, [
            'module' => 'required',
            'fields' => 'required',
        ], [
            'required' => '参数不全',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors()->all()[0];
            return response()->json(['code' => 600, 'message' => $errors]);
        }
        $selectIds=[];
        foreach ($request['fields'] as $key => $value) {
            $Ids=collect($value['fields'])->where('Selected',true)->pluck('ID')->all();
            $selectIds=array_merge($selectIds,$Ids);
        }
        $params['Module']=$request['module'];
        $params['FieldsId']=json_encode($selectIds);
        $curlRequest=['method'=>'POST','params'=>$params,'route'=>'/api/search/fields'];
        $result=HelperClass::curl($curlRequest);
        $res=json_decode($result,true);
        // dd($res);
        // echo "<pre>";
        // var_dump($res,$result);exit;
        if (!isset($res['code'])||$res['code']!=0) {
            return response()->json(['code' => 600, 'message' => '保存搜索条件失败','res'=>$res,'result'=>$result]);
        }
        // $ModulesJson=json_encode([$request['module']]);
        session(['getSearchFields'.$request['module']=>'']);
        return response()->json(['code' => 0]);
    }

    public function flowSelect(Request $request){
        $module=273;
        $userSearchFields = FlowClass::getSearchFields([$module]);
        $fieldList = $userSearchFields[$module];
        return view('flow.flowSelectIndex', ['fieldList' => $fieldList,'module'=>$module]);
    }

    public function flowSelectList(Request $request){

        //自定义搜索 以后整改 感觉自定义搜索应该添加别健属性 用于针对特殊情况
        $module=273;
        $condition=FlowClass::getConditions($request->all(),$module);
        // dd($condition);
        $sql = hlyun_oa_process_flow::
            leftJoin('hlyun_oa_log','hlyun_oa_log.FlowId','=','hlyun_oa_process_flow.ID')
            ->select('hlyun_oa_process_flow.FlowNumber',
            'hlyun_oa_process_flow.AccreditId',
            'hlyun_oa_process_flow.OANumber', 
            'hlyun_oa_process_flow.CreatedAt',
             'hlyun_oa_process_flow.StepStatus', 
             'hlyun_oa_process_flow.Rank', 
             'hlyun_oa_process_flow.ID',
              'hlyun_oa_process_flow.ProcessId')
            ->with(['hasManyCate' => function ($query) {
                $query->select('Name');
            }])
            ->with(['belongsToProce' => function ($query) {
                $query->select('Name', 'ID');
            }])
            // ->with(['hasOneAudit'=>function($query){
            //     $query->select('ID','UserName','StepStatus','FlowId','Sort','StepId')->orderBy('ID','desc');
            // }])
            ->with('hasAllAudit');
        if (isset($request->Type)&&$request->Type != 'all') {
            $sql = $sql->Type($request->Type);
        }
        $request = $request->all();
        // dd($request);
        // $request['FlowNumber']=$request['FlowNumber']?:$request['FlowNumber']
        $page = $this->setPage($request);
        //用户过滤
        $sql = $sql->whereIn('AccreditId', oaUser()->ssoInfo('GroupAccreditIds'));
        if (isset($request['FlowNumber']) && $request['FlowNumber'] != null) {
            $sql = $sql->FlowNumber($request['FlowNumber']);
        }

        if (isset($request['OANumber']) && $request['OANumber'] != null) {
            $sql = $sql->OANumber($request['OANumber']);
        }
        // if (isset($request['CreatedAt']) && $request['CreatedAt'] != null) {
        //     $sql = $sql->CreatedAt($request['CreatedAt']);
        // }
        // if (isset($request['Rank']) && $request['Rank'] != null) {
        //     $sql = $sql->Rank($request['Rank']);
        // }
        // if (isset($request['StepStatus']) && $request['StepStatus'] != null) {
        //     $sql = $sql->StepStatus($request['StepStatus']);
        // }
        if($condition)$sql=$sql->whereRaw($condition);
        $data = $sql->skip($page['skip'])->limit($page['pageSize'])
        //将需要办理事项置顶1,2,5,7 办理 4,3,6已结束
        ->orderByRaw("FIELD(hlyun_oa_process_flow.StepStatus,1,2,5,7,4,3,6)")
        ->orderBy('hlyun_oa_process_flow.UpdatedAt', 'desc')
        ->get()->map(function ($item, $key) {
            //格式整理
            $item = $item->toArray();
            $item['CateName'] = $item['has_many_cate'][0]['Name'];
            $item['ProceName'] = $item['belongs_to_proce']['Name'];
            $item['FlowStepStatus'] = FlowClass::getFlowStepStatus($item['StepStatus'])->param;
            $item['Rank'] = FlowClass::getRank($item['Rank']);
            //办理还是 查看
            $item['FlowStatus'] = FlowClass::getFlowStepStatus($item['StepStatus'])->status;
            //计算第几部
            $item['AuditSort'] = count($item['has_all_audit']);
            $item['has_all_audit'] = end($item['has_all_audit']);
            // dd($item['has_all_audit']);
            $item['AuditUserName'] = FlowClass::getTransactors(
                $item['has_all_audit']['AuditingUserName'],
                $item['has_all_audit']['AuditingTask'],
                $item['has_all_audit']['AuditingRoleName'],
                $item['has_all_audit']['AuditingOrganizationName'],
                $item['has_all_audit']['AuditingMind'],
                $item['has_all_audit']['AuditingMindTitle']
            );
            $item['AuditStepStatus'] = FlowClass::getAuditingStepStatus($item['has_all_audit']['StepStatus']);
            $item['AuditName'] = $item['has_all_audit']['Name'];
            $item['AuditTime'] = $item['has_all_audit']['UpdatedAt'];
            unset($item['has_many_cate']);
            // unset($item['has_all_audit']);
            unset($item['belongs_to_proce']);
            return $item;
        })->toArray();
        $AccreditId=collect($data)->pluck('AccreditId')->toArray();
        
        $Accredit=hlyun_sso_organizations::select('AccreditId','Name')->whereIn('AccreditId',$AccreditId)->get()->keyBy('AccreditId')->toArray();
      
        $data=collect($data)->map(
            function($item) use($Accredit){
                $item['Accredit']=$Accredit[$item['AccreditId']]['Name'];
                return $item;
            }
        )->toArray();
        $count = $sql->count();
        $pageCount = ceil($count / $page['pageSize']);
        if ($count == 0) {
            $startCount = $page['skip'];
        } else {
            $startCount = $page['skip'] + 1;
        }
        $endCount = $page['skip'] + count($data);
        return ['code' => 0, 'data' => $data, 'pageCount' => $pageCount, 'count' => $count, 'startCount' => $startCount, 'endCount' => $endCount];
    }

    public function ownList(Request $request)
    {
        // $validator = \Validator::make($request->all(), [
        //     'Type' => 'required',
        // ], [
        //     'Type.required' => 'Type.不能为空',
        // ]);
        // if ($validator->fails()) {
        //     $errors = $validator->errors()->all()[0];
        //     return response()->json(['code' => 600, 'message' => $errors]);
        // }
        //自定义搜索 以后整改 感觉自定义搜索应该添加别健属性 用于针对特殊情况
        $module=261;
        $condition=FlowClass::getConditions($request->all(),$module);
        // dd($condition);
        $sql = hlyun_oa_process_flow::
            leftJoin('hlyun_oa_log','hlyun_oa_log.FlowId','=','hlyun_oa_process_flow.ID')
            ->select('hlyun_oa_process_flow.FlowNumber','hlyun_oa_process_flow.OANumber', 'hlyun_oa_process_flow.CreatedAt', 'hlyun_oa_process_flow.StepStatus', 'hlyun_oa_process_flow.Rank', 'hlyun_oa_process_flow.ID', 'hlyun_oa_process_flow.ProcessId')
            ->with(['hasManyCate' => function ($query) {
                $query->select('Name');
            }])
            ->with(['belongsToProce' => function ($query) {
                $query->select('Name', 'ID');
            }])
            // ->with(['hasOneAudit'=>function($query){
            //     $query->select('ID','UserName','StepStatus','FlowId','Sort','StepId')->orderBy('ID','desc');
            // }])
            ->with('hasAllAudit');
        if (isset($request->Type)&&$request->Type != 'all') {
            $sql = $sql->Type($request->Type);
        }
        $request = $request->all();
        // dd($request);
        // $request['FlowNumber']=$request['FlowNumber']?:$request['FlowNumber']
        $page = $this->setPage($request);
        //用户过滤
        $sql = $sql->where('UserId', oaUser()->ssoInfo('ID'));
        if (isset($request['FlowNumber']) && $request['FlowNumber'] != null) {
            $sql = $sql->FlowNumber($request['FlowNumber']);
        }

        if (isset($request['OANumber']) && $request['OANumber'] != null) {
            $sql = $sql->OANumber($request['OANumber']);
        }
        // if (isset($request['CreatedAt']) && $request['CreatedAt'] != null) {
        //     $sql = $sql->CreatedAt($request['CreatedAt']);
        // }
        // if (isset($request['Rank']) && $request['Rank'] != null) {
        //     $sql = $sql->Rank($request['Rank']);
        // }
        // if (isset($request['StepStatus']) && $request['StepStatus'] != null) {
        //     $sql = $sql->StepStatus($request['StepStatus']);
        // }
        if($condition)$sql=$sql->whereRaw($condition);
        $data = $sql->skip($page['skip'])->limit($page['pageSize'])
        //将需要办理事项置顶1,2,5,7 办理 4,3,6已结束
        ->orderByRaw("FIELD(hlyun_oa_process_flow.StepStatus,1,2,5,7,4,3,6)")
        ->orderBy('hlyun_oa_process_flow.UpdatedAt', 'desc')
        ->get()->map(function ($item, $key) {
            //格式整理
            $item = $item->toArray();
            $item['CateName'] = $item['has_many_cate'][0]['Name'];
            $item['ProceName'] = $item['belongs_to_proce']['Name'];
            $item['FlowStepStatus'] = FlowClass::getFlowStepStatus($item['StepStatus'])->param;
            $item['Rank'] = FlowClass::getRank($item['Rank']);
            //办理还是 查看
            $item['FlowStatus'] = FlowClass::getFlowStepStatus($item['StepStatus'])->status;
            //计算第几部
            $item['AuditSort'] = count($item['has_all_audit']);
            $item['has_all_audit'] = end($item['has_all_audit']);
            // dd($item['has_all_audit']);
            $item['AuditUserName'] = FlowClass::getTransactors(
                $item['has_all_audit']['AuditingUserName'],
                $item['has_all_audit']['AuditingTask'],
                $item['has_all_audit']['AuditingRoleName'],
                $item['has_all_audit']['AuditingOrganizationName'],
                $item['has_all_audit']['AuditingMind'],
                $item['has_all_audit']['AuditingMindTitle']
            );
            $item['AuditStepStatus'] = FlowClass::getAuditingStepStatus($item['has_all_audit']['StepStatus']);
            $item['AuditName'] = $item['has_all_audit']['Name'];
            $item['AuditTime'] = $item['has_all_audit']['UpdatedAt'];
            unset($item['has_many_cate']);
            // unset($item['has_all_audit']);
            unset($item['belongs_to_proce']);
            return $item;
        })->toArray();
        $count = $sql->count();
        $pageCount = ceil($count / $page['pageSize']);
        if ($count == 0) {
            $startCount = $page['skip'];
        } else {
            $startCount = $page['skip'] + 1;
        }
        $endCount = $page['skip'] + count($data);
        return ['code' => 0, 'data' => $data, 'pageCount' => $pageCount, 'count' => $count, 'startCount' => $startCount, 'endCount' => $endCount];
    }


    //代办事务
    public function delatflow()
    {

        $module=262;
        $userSearchFields = FlowClass::getSearchFields([$module]);
    
        $fieldList = $userSearchFields[$module];
        return view('flow.delatflow', ['fieldList' => $fieldList,'module'=>$module]);
   
    }
    public function delatList(Request $request)
    {
        //能哥的自定义搜索
        $module=262;
        $condition=FlowClass::getConditions($request->all(),$module);

        $sql = hlyun_oa_process_flow_auditing::leftJoin('hlyun_oa_process_flow','hlyun_oa_process_flow_auditing.FlowId','=','hlyun_oa_process_flow.ID')
            ->leftJoin('hlyun_oa_log','hlyun_oa_log.FlowId','=','hlyun_oa_process_flow_auditing.FlowId')
            ->select('hlyun_oa_process_flow_auditing.ID','hlyun_oa_process_flow_auditing.FlowId','hlyun_oa_process_flow_auditing.ProcessId',
                'hlyun_oa_process_flow_auditing.UpdatedAt','hlyun_oa_process_flow.CreatedAt',

                'hlyun_oa_process_flow_auditing.UserName','hlyun_oa_process_flow_auditing.Task',
                'hlyun_oa_process_flow_auditing.RoleName','hlyun_oa_process_flow_auditing.OrganizationName',
                'hlyun_oa_process_flow_auditing.Mind','hlyun_oa_process_flow_auditing.MindTitle','hlyun_oa_process_flow_auditing.StepStatus','hlyun_oa_process_flow_auditing.StepId')
            ->with(['belongsToFlow' => function ($query) {
                $query->select('ID', 'FlowNumber', 'UserName', 'Rank', 'StepStatus', 'OrganizationName','OANumber');
            }])->with(['belongsToStep' => function ($query) {
                $query->select('ID', 'Name');
            }])->with(['belongsToProce' => function ($query) {
                $query->select('ID', 'Name');
            }])->with(['hasManyCate' => function ($query) {
                $query->select('ID', 'Name');
            }]);

        if($condition)$sql=$sql->whereRaw($condition);

        //查询过滤
        $sql = $sql->StepStatus($request->Type);
        $request = $request->all();
        $page = $this->setPage($request);
        //查询搜索
        if (isset($request['FlowNumber']) && $request['FlowNumber'] != null) {
            $sql = $sql->FlowNumber($request['FlowNumber']);
        }

        if (isset($request['OANumber']) && $request['OANumber'] != null) {
            $sql = $sql->OANumber($request['OANumber']);
        }
        // if (isset($request['CreatedAt']) && $request['CreatedAt'] != null) {
        //     $sql = $sql->CreatedAt($request['CreatedAt']);
        // }
        // if (isset($request['Rank']) && $request['Rank'] != null) {
        //     $sql = $sql->Rank($request['Rank']);
        // }
        //用户过滤
        $count = $sql->count();
        $sql = $sql->groupBy('hlyun_oa_process_flow_auditing.FlowId');
        $data = $sql->skip($page['skip'])->limit($page['pageSize'])->orderBy('hlyun_oa_process_flow_auditing.UpdatedAt', 'desc')->get()->map(function ($item, $key) {
            //格式整理
            $item = $item->toArray();
            $item['CateName'] = $item['has_many_cate'][0]['Name'];
            $item['ProceName'] = $item['belongs_to_proce']['Name'];
            $item['FlowNumber'] = $item['belongs_to_flow']['FlowNumber'];
            $item['OANumber'] = $item['belongs_to_flow']['OANumber'];
            $item['FlowStepStatus'] = FlowClass::getFlowStepStatus($item['belongs_to_flow']['StepStatus'])->param;
            $item['Rank'] = FlowClass::getRank($item['belongs_to_flow']['Rank']);
            //办理还是 查看
            $item['FlowStatus'] = FlowClass::getFlowStepStatus($item['belongs_to_flow']['StepStatus'])->status;
            //计算第几部
            $item['ApplyOrganizationName'] = $item['belongs_to_flow']['OrganizationName'];
            $item['ApplyUser'] = $item['belongs_to_flow']['UserName'];
            $item['AuditSort'] = hlyun_oa_process_flow_auditing::where('FlowId', $item['FlowId'])->count();
            $item['AuditUserName'] = FlowClass::getTransactors(
                $item['UserName'],
                $item['Task'],
                $item['RoleName'],
                $item['OrganizationName'],
                $item['Mind'],
                $item['MindTitle']
            );
            // $item['UserName'];
            $item['AuditStepStatus'] = FlowClass::getAuditingStepStatus($item['StepStatus']);
            $item['AuditName'] = $item['belongs_to_step']['Name'];
            $item['AuditTime'] = $item['UpdatedAt'];
            unset($item['has_many_cate']);
            unset($item['belongs_to_flow']);
            unset($item['belongs_to_step']);
            unset($item['belongs_to_proce']);
            return $item;
        })->toArray();
       
        $pageCount = ceil($count / $page['pageSize']);
        if ($count == 0) {
            $startCount = $page['skip'];
        } else {
            $startCount = $page['skip'] + 1;
        }
        $endCount = $page['skip'] + count($data);
        return ['code' => 0, 'data' => $data, 'pageCount' => $pageCount, 'count' => $count, 'startCount' => $startCount, 'endCount' => $endCount];
    }

    public function flowHandleOpenGet(Request $request){
        // dd($request->id);
         
        return view('flow.handle',['id'=>$request->id,'type'=>'getHandle']);
    }

    public function flowHandleOpen(Request $request)
    {
        
        $request = $request->all();
        $validator = Validator::make($request, [
            'id' => 'required',
        ], [
            'id.required' => '参数错误',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors()->all()[0];
            return response()->json(['code' => 600, 'data' => $errors]);
        }
        // dd(hlyun_oa_process_flow::with('hasAllAudit')->find($request['id']));
        $data = hlyun_oa_process_flow::with('hasAllAudit')->find($request['id'])->toArray();
        //如果是未申请事务不显示拒绝并结束
        $refuseAndEnd = $data['StepStatus'] != 1;
        $back = $data['StepStatus'] != 1;
        $data['StepStatus'] = FlowClass::getFlowStepStatus($data['StepStatus'])->param;
        $lastAuditId = hlyun_oa_process_flow_auditing::getLastAuditID($request['id']);
        //步骤整理
        $stepList = hlyun_oa_process_step::where('ProcessId', $data['ProcessId'])->get()->toArray();
        $step = app('\App\Models\hlyun_oa_process_step')->getFlowStep($stepList);
        $processStep = [];
        foreach ($step as $k => $v) {
            $processStep[] = hlyun_oa_process_step::select('ID', 'Name', 'OrganizationName', 'RoleName', 'Task', 'UserName', 'Mind')
                ->whereIn('ID', $v)->orderByRaw(DB::raw("FIND_IN_SET(ID, '" . implode(',', $v) . "'" . ')'))
                ->get()->map(function ($item) {
                    $item = $item->toArray();
                    if (!$item['OrganizationName'] && !$item['RoleName'] && !$item['Task'] && !$item['UserName'] && !$item['Mind']) {
                        $item['Mind'] = "不限制";
                    }
                    return $item;
                })->toArray();
        }
        $auditingStep = $data['has_all_audit'];
        unset($data['has_all_audit']);
        //文件查找
        $filesList = [];
        foreach ($auditingStep as $k => $v) {
            $auditingStep[$k]['AuditingUserName'] = FlowClass::getTransactors(
                $auditingStep[$k]['AuditingUserName'],
                $auditingStep[$k]['AuditingTask'],
                $auditingStep[$k]['AuditingRoleName'],
                $auditingStep[$k]['AuditingOrganizationName'],
                $auditingStep[$k]['AuditingMind'],
                $auditingStep[$k]['AuditingMindTitle']
            );
            $auditingStep[$k]['StepStatus'] = FlowClass::getAuditingStepStatus($auditingStep[$k]['StepStatus']);
            $info = hlyun_oa_process_flow_auditing_files::select('ID', 'FileName as name', 'UserName')->where('AuditingId', $v['AuditingID'])->get()->toArray();
            if (!empty($info)) {

                $filesList[$k]['StepName'] = $v['Name'];
                $filesList[$k]['FileList'] = $info;
            }
        }
        //判断是否是自己事务
        $orSelf = $data['UserId'] == oaUser()->ssoInfo('ID');
        //获取当前节点
        

       
        $StepId = hlyun_oa_process_flow_auditing::find($lastAuditId)->StepId;
        $checkFlowStepPermission = true;
        if (!FlowClass::checkFlowStepPermission($StepId, $data['ID'])) {
            $checkFlowStepPermission = false;
        }
        //补充表单请求信息
        $requestBack=array_except($request,['sso_token','center_token']);
        $FormFieldTemp=$data['FormField'];
        //清除旧参数
        foreach($requestBack as $k=>$v){
            request()->offsetUnset($k);
        }

        foreach($FormFieldTemp as $k=>$v){
            request()->offsetSet($k, $v);
        }
        $stepData = array_merge(FlowClass::getStepData($StepId), $data['FormField']);
         //移除参数
        foreach($FormFieldTemp as $k=>$v){
            request()->offsetUnset($k);
        }
        //清除旧参数
        foreach($requestBack as $k=>$v){
            request()->offsetSet($k,$v);
        }
        //是否进行数据加锁
        //默认是第一步类型的话不禁用输入框
        $dataLock = hlyun_oa_process_step::find($StepId)->DataLock == 2 ? true : false;
        //返回步骤初始调用数据
        if (hlyun_oa_process_step::find($StepId)->Type == 2) $dataLock = false;
        // dd(1);
        return [
            'code' => 0, 'stepData' => $stepData, 'checkFlowStepPermission' => $checkFlowStepPermission,
            'dataLock' => $dataLock, 'orSelf' => $orSelf, 'refuseAndEnd' => $refuseAndEnd,
            'back' => $back, 'data' => $data,
            'AuditingId' => $lastAuditId,
            'StepId' => $StepId,
            'processStep' => $processStep,
            'filesList' => $filesList,
            'auditingStep' => $auditingStep
        ];
    }
    //节点提交统一处理
    public function flowHandleSub()
    {
        $request = \Request::all();
        $validator = Validator::make($request, [
            'AuditingId' => 'required',
            'StepId' => 'required',
            'Type' => 'required',
        ], [
            'AuditingId.required' => '当前审核Id不存在',
            'StepId.required' => '提交步骤Id不存在',
            'Type.required' => '提交类型异常',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors()->all()[0];
            return response()->json(['code' => 600, 'data' => $errors]);
        }
        $auditing = hlyun_oa_process_flow_auditing::with('belongsToStep')->find($request['AuditingId']);
        $flow = hlyun_oa_process_flow::find($auditing->FlowId);
        //核实当前用户身份
        if (!FlowClass::checkFlowStepPermission($auditing->StepId, $auditing->FlowId) && !($request['Type'] == 4 && $flow->UserId == oaUser()->ssoInfo('ID'))) {
            return ['code' => 600, 'data' => '流程当前步骤审核人身份设定与你不合符，无法办理！'];
        }

        //防止重复提交检测当前步骤是否与最新ID重复
        if (FlowClass::checkFlowStepRepeat($auditing->FlowId, $request['StepId'])) {
            return ['code' => 600, 'data' => '当前事务流程检测存在重复步骤或未提交申请'];
        }

        //查看流程是否被锁
        if (FlowClass::getFlowStepStatus($flow->StepStatus)->lock) {
            return ['code' => 600, 'data' => '当前事务流程已锁定不可操作'];
        }
       
        \DB::beginTransaction();
        try {
            $step = hlyun_oa_process_step::find($request['StepId']);
            $mindId = $step->MindId;
            $mindTitle = $step->MindTitle;
            $task = '';
            $taskId = '';
            $organizationId = '';
            $AccreditId = $flow->OrganizationId;
            if (isset($flow->FormField['WorkNumber'])) {
                $OrderAccreditId = OrderClass::getOrderAccreditId($flow->FormField['WorkNumber']);
            } else {
                // throw new OAException('审核过程未索引字段《WorkNumber》请检查该流程办理人员设定是否合理');
                $OrderAccreditId = '';
            }
            //判断办理设置
            if ($step->HandleType == 6) {
                if (isset($flow->FormField[$step->MindId])) {
                    $mindId = $flow->FormField[$step->MindId];
                } else {
                    throw new OAException('未索引办理人员->校验字段《' . $step->MindId . '》请检查该流程办理人员设定是否合理');
                }

                $mindTitle = $flow->FormField[$step->MindTitle] ?? '';
                if ($step->MindSource == 'higher') {
                    //必须是订单业务才有所属直属上司
                    if (!isset($flow->FormField['WorkNumber'])) throw new OAException("不存在订单号无法追踪所属企业!请联系管理员流程办理人员设定是否合理");
                    $higher = FlowClass::getHigher($flow->FormField['WorkNumber'], $flow->FormField[$step->MindId]);
                    $mindId = $higher['ID'];
                    $mindTitle = $higher['Name'];
                }
            } else if ($step->HandleType == 3) {
                $taskId = collect($step->TaskId)->map(function ($item, $key) use ($AccreditId, $OrderAccreditId) {
                    //替换0 -1
                    if (preg_match('/^-1\//', $item)) $item = preg_replace('/^-1\//', $AccreditId . '/', $item);
                    if (preg_match('/^0\//', $item)) $item = preg_replace('/^0\//', $OrderAccreditId . '/', $item);
                    return $item;
                })->toArray();
                $Accredit = hlyun_sso_organizations::where('AccreditId', $AccreditId)->value('Name');
                $OrderAccredit = hlyun_sso_organizations::where('AccreditId', $OrderAccreditId)->value('Name');
                if (preg_match('/^当前订单业务企业\//', $step->Task)) $task = preg_replace('/^当前订单业务企业/', $OrderAccredit, $step->Task);
                if (preg_match('/^当前申请人企业\//', $step->Task)) $task = preg_replace('/^当前申请人企业/', $Accredit, $step->Task);
                // dd($task,$AccreditId,$OrderAccreditId,$Accredit,$OrderAccredit);
            } else if ($step->HandleType == 4) {
                $organizationId = collect($step->OrganizationId)->map(function ($item) use ($AccreditId, $OrderAccreditId) {
                    //替换0 -1
                    if ($item == 0) $item = $OrderAccreditId;
                    if ($item == -1) $item = $AccreditId;
                    return $item;
                })->toArray();
            }

            //修改当前审核节点办理人
            $auditing->AccreditId= oaUser()->ssoInfo('AccreditId');
            $auditing->OrganizationId = oaUser()->ssoInfo('CompanyId');
            $auditing->OrganizationName = oaUser()->ssoInfo('Company');
            $auditing->RoleName = oaUser()->ssoInfo('Roles');
            $auditing->RoleId = implode(',', oaUser()->ssoInfo('RoleIds' ?: []));
            // $auditing->Task = oaUser()->ssoInfo('Task');
            $auditing->Task = "";
            $auditing->UserId = oaUser()->ssoInfo('ID');
            $auditing->UserName = oaUser()->ssoInfo('Name');
            //判断是否当前步骤为子流程
            if ($step->Type == 3) {
                //创建子流程2019-8-6未完善
                //锁定当前事务
                hlyun_oa_process_flow_auditing::create([
                    'StepId' => $step->ID,
                    'ProcessId' => $step->ProcessId,
                    'FlowId' => $auditing->FlowId,
                    'StepStatus' => 1,
                    'OrganizationId' =>  implode(',', ($organizationId ?: [])),
                    'OrganizationName' => $step->OrganizationName,
                    'RoleName' => $step->RoleName,
                    'RoleId' => implode(',', ($step->RoleId ?: [])),
                    'Task' => $task,
                    'TaskId' =>  implode(',', ($taskId ?: [])),
                    'UserId' =>  implode(',', ($step->UserId ?: [])),
                    'UserName' => $step->UserName,
                    'Mind' => $step->Mind,
                    'MindId' => $mindId,
                    'MindTitle' => $mindTitle,
                    'MindType' => $step->MindType,
                ]);
                $auditing->StepStatus = 2;
                $auditing->save();
                $flow->StepStatus = 7;
                $flow->save();
                \DB::commit();
                return ['code' => 0, 'data' => "流程审核进入子流程审批,等待解锁审批"];
            }
            //记录入库 2 提交 剩余为回退
            switch ($request['Type']) {
                case 2:
                    //判断是否最后一个节点结束事务
                    if ($step->To) {
                        
                        //待办理办理人
                        $CurrentId='';
                        if (!empty($step->UserId)) {
                            $CurrentId=is_array($step->UserId)?implode(',',$step->UserId):$step->UserId;
                        } elseif (!empty($mindId)) {
                            $CurrentId=is_array($mindId)?implode(',', $mindId):$mindId;
                        } elseif (!empty($taskId)) {
                            //切换职务索引
                            $map = [];
                            foreach ($taskId as $v) {
                                $map[] = [
                                    ['AccreditId', '=', substr($v, 0, strpos($v, '/'))],
                                    ['Position', '=', substr($v, strpos($v, '/') + strlen('/'))]
                                ];
                                //补充集团职务信息
                                // $map[] = [
                                //     ['AccreditId', '=', oaUser()->ssoInfo('GroupAccreditId')],
                                //     ['Position', '=', substr($v, strpos($v, '/') + strlen('/'))]
                                // ];
                            }

                            $positionId = hlyun_sso_position::where(function ($query) use ($map) {
                                $sql = $query;
                                foreach ($map as $v) {
                                    $sql = $sql->OrWhere($v);
                                }
                                $query = $sql;
                            })->pluck('ID')->toArray();
                            if(empty($positionId)){
                                //   \DB::rollback();
                                //  return ['code' => 600, 'data' => $task."为空,请检测职务是否存在该企业中"];
                            }
                            $userId = hlyun_sso_index_position_user::whereIn('PositionId', $positionId)->pluck('UserId')->toArray();
                             if(empty($userId)){
                                // \DB::rollback();
                                // return ['code' => 600, 'data' => "未检测该职务绑定用户或请检测职务是否存该企业中"];
                            }
                            if(!empty($userId)){
                                $name=hlyun_sso_users::whereIn('ID',$userId)->pluck('Name')->toArray();
                                $task=implode(',', $name);
                            }
                            $CurrentId=implode(',', $userId);
                            unset($userId);
                            unset($map);
                        }
                        // dd($CurrentId);
                        $flow->StepStatus = 2;
                        $auditing->StepStatus = 2;
                        hlyun_oa_process_flow_auditing::create([
                            'StepId' => $step->ID,
                            'ProcessId' => $step->ProcessId,
                            'FlowId' => $auditing->FlowId,
                            'StepStatus' => 1,
                            'OrganizationId' =>  implode(',', ($organizationId ?: [])),
                            'OrganizationName' => $step->OrganizationName,
                            'RoleName' => $step->RoleName,
                            'RoleId' => implode(',', ($step->RoleId ?: [])),
                            'Task' => $task,
                            'TaskId' =>  implode(',', ($taskId ?: [])),
                            'UserId' =>  implode(',', ($step->UserId ?: [])),
                            'UserName' => $step->UserName,
                            'Mind' => $step->Mind,
                            'MindId' => $mindId,
                            'MindTitle' => $mindTitle,
                            'MindType' => $step->MindType,
                            'CurrentId'=>$CurrentId
                        ]);
                        $msg="流程审核进入下个节点,已通知审批用户进行处理";
                    } else {
                        hlyun_oa_process_flow_auditing::create([
                            'StepId' => $step->ID,
                            'ProcessId' => $step->ProcessId,
                            'FlowId' => $auditing->FlowId,
                            'StepStatus' => 2,
                            'AccreditId'=> oaUser()->ssoInfo('AccreditId'),
                            'OrganizationId' => oaUser()->ssoInfo('CompanyId'),
                            'OrganizationName' => oaUser()->ssoInfo('Company'),
                            'RoleName' => oaUser()->ssoInfo('Roles'),
                            'RoleId' => implode(',', (oaUser()->ssoInfo('RoleIds') ?: [])),
                            // 'Task' => oaUser()->ssoInfo('Task'),
                            'Task' => "",
                            'UserId' => oaUser()->ssoInfo('ID'),
                            'UserName' => oaUser()->ssoInfo('Name'),
                            'CurrentId'=>$flow->UserId,
                        ]);
                        $auditing->StepStatus = 2;
                       
                        $flow->StepStatus = 3;
    
                        
                        $msg="流程审核结束,已通知申请用户";
                    }
                    //当前流转的步骤查询
                    $to = "$auditing->StepId-$request[StepId]";
                    //记录log
                  
                    //进入转出条件设置
                    FlowClass::outCondition($to, $auditing, $flow, $request);
                    $flow->FormField = $flow->FormFieldTemp;
                    $flow->ApplyTable = $flow->ApplyTableTemp;
                    $auditing->save();
                    $flow->save();
                    \DB::commit();
                    //结束通知
                    return ['code' => 0, 'data' =>$msg ];
                    break;
                case 4:
                case 1:
                    //回退节点处理
                    //回退第一步
                    if ($step->Type == 2) {
                        hlyun_oa_process_flow_auditing::create([
                            'StepId' => $step->ID,
                            'ProcessId' => $step->ProcessId,
                            'FlowId' => $auditing->FlowId,
                            'StepStatus' => 1,
                            'OrganizationId' => $flow->OrganizationId,
                            'OrganizationName' => $flow->OrganizationName,
                            'RoleName' => $flow->RoleName,
                            'RoleId' => $flow->RoleId,
                            'Task' => $flow->Task,
                            'UserId' => $flow->UserId,
                            'UserName' => $flow->UserName,
                            'CurrentId'=>$flow->UserId,
                        ]);
                        //审核被退回通知
                    } else {
                        
                        //待办理通知
                        $CurrentId='';
                        if (!empty($step->UserId)) {
                            $CurrentId=is_array($step->UserId)?implode(',',$step->UserId):$step->UserId;
                        } elseif (!empty($mindId)) {
                            $CurrentId=is_array($mindId)?implode(',', $mindId):$mindId;
                        } elseif (!empty($taskId)) {
                            //切换职务索引
                            $map = [];
                            foreach ($taskId as $v) {
                                $map[] = [
                                    ['AccreditId', '=', substr($v, 0, strpos($v, '/'))],
                                    ['Position', '=', substr($v, strpos($v, '/') + strlen('/'))]
                                ];
                            }

                            $positionId = hlyun_sso_position::where(function ($query) use ($map) {
                                $sql = $query;
                                foreach ($map as $v) {
                                    $sql = $sql->OrWhere($v);
                                }
                                $query = $sql;
                            })->pluck('ID')->toArray();
                            $userId = hlyun_sso_index_position_user::whereIn('PositionId', $positionId)->pluck('UserId')->toArray();
                            $CurrentId=implode(',', $userId);
                            unset($userId);
                            unset($map);
                        }
                         hlyun_oa_process_flow_auditing::create([
                            'StepId' => $step->ID,
                            'ProcessId' => $step->ProcessId,
                            'FlowId' => $auditing->FlowId,
                            'StepStatus' => 1,
                            'OrganizationId' =>  implode(',', ($organizationId ?: [])),
                            'OrganizationName' => $step->OrganizationName,
                            'RoleName' => $step->RoleName,
                            'RoleId' => implode(',', ($step->RoleId ?: [])),
                            'Task' => $task,
                            'TaskId' =>  implode(',', ($taskId ?: [])),
                            'UserId' =>  implode(',', ($step->UserId ?: [])),
                            'UserName' => $step->UserName,
                            'Mind' => $step->Mind,
                            'MindId' => $mindId,
                            'MindTitle' => $mindTitle,
                            'MindType' => $step->MindType,
                            'CurrentId'=>$CurrentId,
                        ]);
                    }
                   
                    //进入回退条件设置
                    FlowClass::backCondition($auditing->belongsToStep, $flow, $request);
                    $flow->FormField = $flow->FormFieldTemp;
                    $flow->ApplyTable = $flow->ApplyTableTemp;

                    $auditing->StepStatus = 4;
                    $flow->StepStatus = 5;
                    $flow->save();
                    $auditing->save();

                    \DB::commit();

                    return ['code' => 0, 'data' => "流程审核回退,已通知处理节点用户"];
                    break;
            }
            return ['code' => 600, 'data' => '未进入选项'];
        } catch (\Throwable $t) {
            // dd($t);
            \DB::rollback();
            return ['code' => 600, 'data' => '节点流转失败：'.$t->getMessage(),'getTraceAsString'=>$t->getTraceAsString()];
        }
    }
   

    //办理提交返回流转步骤
    public function flowAuditing(Request $request)
    {
        $request = $request->all();
        $validator = Validator::make($request, [
            'AuditingId' => 'required',
            'StepStatus' => 'required',
            'ApplyTable' => 'required',
        ], [
            'AuditingId.required' => '当前审核Id不存在',
            'StepStatus.required' => '提交步骤状态请声明',
            'ApplyTable.required' => '提交表单信息不能为空',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors()->all()[0];
            return response()->json(['code' => 600, 'data' => $errors]);
        }
        //返回审核消息
        $auditing = hlyun_oa_process_flow_auditing::find($request['AuditingId']);
        $flow = hlyun_oa_process_flow::find($auditing->FlowId);
        //核实当前用户身份
        if ((!FlowClass::checkFlowStepPermission($auditing->StepId, $auditing->FlowId) && $request['StepStatus'] != 5)
            && !($request['StepStatus'] == 4 && $flow->UserId == oaUser()->ssoInfo('ID'))
        ) {
            return ['code' => 600, 'data' => '流程当前步骤审核人身份设定与你不合符，无法办理！'];
        }

        //查看流程是否被锁
        if (FlowClass::getFlowStepStatus($flow->StepStatus)->lock) {
            return ['code' => 600, 'data' => '当前事务流程已锁定不可操作'];
        }
        \DB::beginTransaction();
        try {
            $nodeInfo = [];
            switch ($request['StepStatus']) {
                case 5:
                    //判断该事务发起人是否符合身份
                    $auditing->StepStatus = 3;
                    $auditing->UserName = oaUser()->ssoInfo('Name');
                    $auditing->UserId = oaUser()->ssoInfo('ID');
                    $auditing->save();
                    $flow = hlyun_oa_process_flow::find($auditing->FlowId);
                    if ($flow->UserId != oaUser()->ssoInfo('ID')) {
                        return ['code' => 600, 'data' => '只有事务流程申请者才能取消申请,请选择回退或拒绝的操作!'];
                    }
                    $flow->StepStatus = 6; //标记取消申请
                    $flow->save();
                    break;
                case 4:
                    //回退重新提交
                    $nodeInfo = hlyun_oa_process_step::select(
                        'ID',
                        'Name',
                        'OrganizationName',
                        'RoleName',
                        'Task',
                        'UserName',
                        'Mind',
                        'MindTitle'
                    )->StepFirst($auditing->ProcessId)->get()
                        ->map(function ($item, $key) use ($flow) {
                            //格式整理
                            $item = $item->toArray();
                            $item['UserName'] = FlowClass::getTransactors($flow->UserName, $flow->Task, $flow->RoleName, $flow->OrganizationName);
                            return $item;
                        })
                        ->toArray();
                    break;
                case 3:
                    // 拒绝审核并结束流程
                    $auditing->StepStatus = 3;
                    $auditing->UserName = oaUser()->ssoInfo('Name');
                    $auditing->UserId = oaUser()->ssoInfo('ID');
                    $auditing->save();
                    $flow = hlyun_oa_process_flow::find($auditing->FlowId);
                    $flow->StepStatus = 4; //标记取消申请
                    $flow->save();
                    //审核被退回通知
                    break;
                case 2:
                    //返回下一步剩下节点信息
                    $nodeInfo = hlyun_oa_process_step::select(
                        'ID',
                        'Name',
                        'OrganizationName',
                        'RoleName',
                        'Task',
                        'UserName',
                        'Mind',
                        'MindId',
                        'MindTitle',
                        'MindSource'
                    )->whereIn('ID', hlyun_oa_process_step::getFlowNextStep($auditing->ProcessId, $auditing->StepId))->get()
                        ->map(function ($item, $key) use ($flow) {
                            //格式整理
                            $item = $item->toArray();
                            $item['MindTitle'] = $flow->FormField[$item['MindTitle']] ?? '';
                            if ($item['MindSource'] == 'higher') {
                                if (!isset($flow->FormField['WorkNumber'])) throw new OAException("不存在订单号无法追踪所属企业!请联系管理员流程办理人员设定是否合理");
                                $higher = FlowClass::getHigher($flow->FormField['WorkNumber'], $flow->FormField[$item['MindId']]);
                                $item['MindTitle'] = $higher['Name'];
                            }
                            //必须是订单业务才有所属直属上司
                            $item['UserName'] = FlowClass::getTransactors($item['UserName'], $item['Task'], $item['RoleName'], $item['OrganizationName'], $item['Mind'], $item['MindTitle']);
                            return $item;
                        })->toArray();
                    break;
                case 1:
                    //返回回退剩下节点信息
                    $nodeInfo = hlyun_oa_process_step::select(
                        'ID',
                        'Name',
                        'OrganizationName',
                        'RoleName',
                        'Task',
                        'UserName',
                        'Mind',
                        'MindId',
                        'MindTitle',
                        'MindSource'
                    )->whereIn('ID', hlyun_oa_process_step::getFlowBeforeStep($auditing->ProcessId, $auditing->StepId))->get()
                        ->map(function ($item, $key) use ($flow) {
                            //格式整理
                            $item = $item->toArray();
                            $item['MindTitle'] = $flow->FormField[$item['MindTitle']] ?? '';
                            if ($item['MindSource'] == 'higher') {
                                if (!isset($flow->FormField['WorkNumber'])) throw new OAException("不存在订单号无法追踪所属企业!请联系管理员流程办理人员设定是否合理");
                                $higher = FlowClass::getHigher($flow->FormField['WorkNumber'], $flow->FormField[$item['MindId']]);
                                $item['MindTitle'] = $higher['Name'];
                            }
                            $item['UserName'] = FlowClass::getTransactors($item['UserName'], $item['Task'], $item['RoleName'], $item['OrganizationName'], $item['Mind'], $item['MindTitle']);
                            return $item;
                        })
                        ->toArray();
                    break;
            }
            $auditing->Feedback = $request['Feedback'];
            $auditing->save();
            $flow->FlowNumber = $request['FlowNumber'];
            //数据临时存储 数据锁定不允许修改
            if (hlyun_oa_process_step::IsDataLock($auditing->StepId)) {
                $flow->ApplyTableTemp = $request['ApplyTable'];
                parse_str($request['ApplyTableData'], $FormFieldTemp);
                $flow->FormFieldTemp = $FormFieldTemp;
                $flow->FormField = $flow->FormFieldTemp;
                $flow->ApplyTable = $flow->ApplyTableTemp;
            }
            $flow->save();
            \DB::commit();
            return ['code' => 0, 'data' => '流程内容已保存!', 'nodeInfo' => $nodeInfo, 'auditingId' => $auditing->ID];
        } catch (\Throwable $t) {
            // dd($t);
            \DB::rollback();
            if ($t instanceof OAException) {
                throw new OAException($t->getMessage());
            }
            throw new \App\Exceptions\OAException('节点信息查询错误:' . $t->getLine() .$t->getMessage(). $t->getTraceAsString());
        }
    }

    //联动数据动态获取
    public function getDynamic(Request $request)
    {

        $request = $request->all();
        $validator = Validator::make($request, [
            'StepId' => 'required',
            'ObjId' => 'required',
        ], [
            'StepId.required' => '当前步骤ID不存在',
            'ObjId.required' => '联动对象Id不存在',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors()->all()[0];
            return response()->json(['code' => 600, 'data' => $errors]);
        }

        $setting = hlyun_oa_process_step::find($request['StepId'])->Setting;
        // $infos = $setting[$request['ObjId']] ?? [];
        $infos =$setting;
        $data = [];
        if ($infos) {
            $requestBack = array_except($request, ['sso_token', 'center_token']);;
            $FormFieldTemp = $request['ApplyData'] ?? [];
            //清除旧参数
            foreach ($requestBack as $k => $v) {
                request()->offsetUnset($k);
            }
            //新参数
            foreach ($FormFieldTemp as $k => $v) {
                request()->offsetSet($k, $v);
            }

            foreach ($infos as $info) {

                if ($info['objId'] != $request['ObjId']) {
                    continue;
                }


                //值合并
                foreach ($info['getApi'] as $v) {
                    if ($v) {
                        try {
                            $res = app('\App\Api\Controller\ApiController')->requestId($v);
                            if (isset($res['code']) && $res['code'] == 600) throw new OAException($res['data']);
                            //如果存在字段则绑定进入联动字段
                            if (!empty($info['formField'])) {
                                foreach ($info['formField'] as $vv) {
                                    $data[$vv] = $res[$vv] ?? $res;
                                    if ($res[$vv] ?? false) unset($res[$vv]);
                                }
                            } else {
                                $data = array_merge($res, $data);
                            }
                        } catch (\Throwable $t) {
                            // dd($t);
                            if ($t instanceof OAException) {
                                throw new OAException($t->getMessage());
                            }
                            throw new OAException("动态获取操作API编号：{$v}《" . hlyun_oa_api::find($v)->Name . "》存在异常，请联系开发人员解决");
                        }
                    }
                }
            }

            //移除参数
            foreach ($FormFieldTemp as $k => $v) {
                request()->offsetUnset($k);
            }
            //清除旧参数
            foreach ($requestBack as $k => $v) {
                request()->offsetSet($k, $v);
            }
        } else {
            return [];
            throw new OAException("《数据动态》获取未检索到联动事件,有需求请联系开发人员更改流程节点设定");
        }



        //根据objid查找内容
        return $data;
    }
    //设置页码
    public function setPage($get)
    {

        $page = isset($get['page']) ? $get['page'] : 1;
        $pageSize = isset($get['spage']) ? $get['spage'] : 10;
        $skip = ($page - 1) * $pageSize;
        return ['pageSize' => $pageSize, 'skip' => $skip, 'page' => $page];
    }
      /**
     *  搜索默认字段
     * @Author   Allen
     * @DateTime 2019-12-25
     * @return   [type]     [description]
     */
    public function getDefaultFields(){
        $request=\Request::all();
        // dd($request);
        $validator = \Validator::make($request, [
            'module' => 'required',
        ], [
            'required' => '参数不全',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors()->all()[0];
            return response()->json(['code' => 600, 'message' => $errors]);
        }
        $module=$request['module'];
        $userDefaultField = FlowClass::getSearchDefaultFields([$module]);
        // dd($userDefaultField);
        $fieldList = $userDefaultField[$module];
        return response()->json(['code'=>0,'data'=>$fieldList]);
    }
}
