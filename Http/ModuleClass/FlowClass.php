<?php
namespace App\Http\ModuleClass;
use QL\QueryList;
use App\Models\hlyun_oa_process_step;
use App\Models\hlyun_oa_process_flow_auditing;
use App\Exceptions\OAException;
use App\Models\hlyun_oa_api;
use App\Models\hlyun_oa_process_flow;
use App\Models\Oms\hlyun_order_orders;
use App\Models\Trailer\hlyun_order_orders as hlyun_order_orders_wp;
use App\Models\Sso\hlyun_sso_users;
use App\Models\Sso\hlyun_sso_index_organization_user;
use App\Models\Sso\hlyun_sso_organizations;
use Mockery\Expectation;

class FlowClass {
    // 紧急程度：1正常 2重要 3紧急
    public static function getRank($param)
    {
        switch ($param) {
            case  1:
                $param = '正常';
                break;
            case 2:
                $param = '重要';
                break;
            case 3:
                $param = '紧急';
                break;
        }
        return $param;
    }
    // 办理进度（1：保存未申请；2：办理中 3：完结通过 4：拒绝失败已结束 5：回退重新提交 6：取消申请已结束）
    public static function getFlowStepStatus($param)
    {
        $status = 'ing';
        switch ($param) {
            case  1:
                $param = '保存未申请';
                $lock = false;
                break;
            case 2:
                $param = '办理中';
                $lock = false;
                break;
            case 3:
                $param = '完结通过';
                $status = 'end';
                $lock = true;
                break;
            case 4:
                $param = '审核不通过已结束';
                $status = 'end';
                $lock = true;
                break;
            case 5:
                $param = '回退重新提交';
                $lock = false;
                break;
            case 6:
                $param = '取消申请已结束';
                $status = 'end';
                $lock = true;
                break;
            case 7:
                $param = '子流程锁定中';
                $lock = true;
                break;
        }
        $obj = new \stdClass();
        $obj->param = $param;
        $obj->status = $status;
        $obj->lock = $lock;
        return $obj;
    }
    // 办理进度（1：办理待处理；2:完结通过；3：完结不通过 ；4：回退不通过重新提交  ）
    public static function getAuditingStepStatus($param)
    {
        switch ($param) {
            case  1:
                $param = '办理待处理';
                break;
            case 2:
                $param = '完结通过';
                break;
            case 3:
                $param = '完结不通过';
                break;
            case 4:
                $param = '回退不通过重新提交 ';
                break;
        }
        return $param;
    }

    // 办理人员类型 1指定人员 2指定角色 3指定职务 4指定组织 5不限制 6智能匹配
    public static function checkFlowStepPermission($StepId,$FlowId)
    {   
        if(request()->server('HTTP_HOST')=='localhost'){
            return true;
        }
       
        // 'AccreditId'=>0,// 'Name'=>'《智能匹配当前订单业务企业》'// 'AccreditId'=>-1, // 'Name'=>'《智能匹配当前申请人企业》'
        if(empty($FlowId)){
            $AccreditId=oaUser()->ssoInfo('AccreditId');
            $OrderAccreditId=oaUser()->ssoInfo('AccreditId');
        }else{
            $Flow=hlyun_oa_process_flow::find($FlowId);
            $AccreditId=$Flow->OrganizationId;
            if(isset($Flow->FormField['WorkNumber'])){
                self::isWp($Flow->FormField['WorkNumber'])&&$OrderAccreditId=hlyun_order_orders_wp::where('WorkNumber',$Flow->FormField['WorkNumber'])->value('AccreditId');
                !self::isWp($Flow->FormField['WorkNumber'])&&$OrderAccreditId=hlyun_order_orders::where('WorkNumber',$Flow->FormField['WorkNumber'])->value('AccreditId');

            }else{
                $OrderAccreditId='';
            }
        }
        $info = hlyun_oa_process_step::find($StepId);
        switch ($info->HandleType) {
            case 1:
                // oaUser()->ssoInfo('ID');
                if (empty($info['UserId'])||in_array(oaUser()->ssoInfo('ID'), $info['UserId'])) {
                    return true;
                }
                return false;
                break;
            case 2:
                $lock = false;
                foreach (oaUser()->ssoInfo('RoleIds') as $k => $v) {
                    if (empty($info['RoleId'])||in_array($v, $info['RoleId'])) {
                        $lock = true;
                        break;
                    }
                }
                return $lock;
                break;
            case 3:      
                //TaskId
                if(empty($info['TaskId'])){
                    return true;
                }
                $lock = false;
                $info['TaskId']=collect($info['TaskId'])->map(function($item, $key) use($AccreditId,$OrderAccreditId){
                    //替换0 -1
                    $item=preg_replace('/^-1\//',$AccreditId.'/',$item);
                    $item=preg_replace('/^0\//',$OrderAccreditId.'/',$item);
                    return $item;                   
                })->toArray();
                //查询组装
                $tasks=[];
                $task=explode("+",oaUser()->ssoInfo('Task'));
                foreach (oaUser()->ssoInfo('GroupAccreditIds') as $k => $v) {
                    foreach($task as $vv){
                        array_push($tasks,$v.'/'.$vv);
                    }
                }
                if (array_intersect($tasks,$info['TaskId'])){
                    $lock = true;
                }
                return $lock;
                break;
            case 4:
                if(empty($info['OrganizationId'])){
                    return true;
                }
                $info['OrganizationId']=collect($info['OrganizationId'])->map(function($item) use($AccreditId,$OrderAccreditId){
                    //替换0 -1
                    if($item==0)$item=$OrderAccreditId;
                    if($item==-1)$item=$AccreditId;
                    return $item;                   
                })->toArray();
                $lock = false;
                foreach (oaUser()->ssoInfo('GroupAccreditIds') as $k => $v) {
                    if (empty($info['OrganizationId'])||in_array($v, $info['OrganizationId'])) {
                        $lock = true;
                        break;
                    }
                }
                return $lock;
                break;
            case 5:
                return true;
                break;
            case 6:
                if(isset($Flow->FormField[$info->MindId])){
                    // dd($info->MindSource);
                    if($info->MindSource=='higher'){
                        //必须是订单业务才有所属直属上司
                        if(!isset($Flow->FormField['WorkNumber']))throw new OAException("不存在订单号无法追踪所属企业!请联系管理员流程办理人员设定是否合理");
                        $higher=self::getHigher($Flow->FormField['WorkNumber'],$Flow->FormField[$info->MindId]);
                        
                        if($higher['ID']!=oaUser()->ssoInfo('ID')){
                            return false;
                        }else{
                            return true;
                        }
                    }
                    if($info->MindType=="ID"&&$Flow->FormField[$info->MindId]!=oaUser()->ssoInfo('ID')){
                       return false;
                    }
                    if($info->MindType=="Name"&&$Flow->FormField[$info->MindId]!=oaUser()->ssoInfo('Name')){
                        return false;
                    }
                    if($info->MindType=="Accredit"&&$Flow->FormField[$info->MindId]!=oaUser()->ssoInfo('AccreditId')){
                        return false;
                    }
                    // dd($info->MindId);   
                }else{
                    throw new OAException('未索引办理人员->校验字段《'.$info->MindId.'》请检查该流程办理人员设定是否合理');
                }
                      
                return true;
                break;
        }
    }
    //获取用户直属上司信息
    public static function getHigher($WorkNumber,$UserId){
        
        !self::isWp($WorkNumber)&&$AccreditId=hlyun_order_orders::where('WorkNumber',$WorkNumber)->value('AccreditId');
        self::isWp($WorkNumber)&&$AccreditId=hlyun_order_orders_wp::where('WorkNumber',$WorkNumber)->value('AccreditId');
        $OrganizationId=hlyun_sso_organizations::where('AccreditId',$AccreditId)->value('ID');
        $higher=hlyun_sso_users::leftJoin(
            'hlyun_sso_index_organization_user','hlyun_sso_users.ID','=','hlyun_sso_index_organization_user.Pid'
        )
        ->select('hlyun_sso_users.ID','hlyun_sso_users.Name')
        ->where('hlyun_sso_index_organization_user.UserId',$UserId)
        ->where('hlyun_sso_index_organization_user.OrganizationId',$OrganizationId)
        // ->where('hlyun_sso_index_organization_user.OrganizationId',1)
        ->first();
        if(!$OrganizationId)throw new OAException("订单的所属企业信息为空!请检测订单数据合法性");

        // dd($higher,$UserId);
        if(!$higher)throw new OAException("用户《".hlyun_sso_users::find($UserId)->Name."》检测直属上司为空！请检查该《".hlyun_sso_organizations::find($OrganizationId)->Name."》组织关系设定");
        return $higher;
    }

    //获取当前步骤的id
    public static function getStepData($id)
    {
        // dd(hlyun_oa_process_step::find($id));
        $field = hlyun_oa_process_step::find($id)->Field;
        $field = is_array($field) ? $field : [];
        $InitDataApi=hlyun_oa_process_step::find($id)->InitDataApi;
        $result = [];
        if(!empty($InitDataApi)){
            try{
                $res=app('\App\Api\Controller\ApiController')->requestId($InitDataApi);
                if(isset($res['code'])&&$res['code']==600)throw new OAException($res['data']);
                $result =$res;
            }catch(\Throwable $t){
                // dd($t);
                if($t instanceof OAException){
                    throw new OAException($t->getMessage());
                }
                throw new OAException("初始化数据获取API编号：{$InitDataApi}《".hlyun_oa_api::find($InitDataApi)->Name."》存在异常");
            }
        }
        // dd($result);
        foreach ($field as $k => $v) {
            //存在api将去覆盖默认值
            $result[$v['name']] = $result[$v['name']]??$v['value'];
            if ($v['api']) {
                try{
                    $res=app('\App\Api\Controller\ApiController')->requestId($v['api']);
                    if(isset($res['code'])&&$res['code']==600)throw new OAException($res['data']);
                    $result[$v['name']] =$res;
                }catch(\Throwable $t){
                    // dd($t);
                    if($t instanceof OAException){
                        throw new OAException($t->getMessage());
                    }
                    throw new OAException("初始化表单获取API编号：{$v['api']}《".hlyun_oa_api::find($v['api'])->Name."》存在异常");
                }
            }
        }
        return $result;
        // dd($result);
    }
    //检测当前提交步骤与最新步骤是否重复
    public static function checkFlowStepRepeat($flowId, $stepId)
    {
        return hlyun_oa_process_flow_auditing::where('FlowId', $flowId)->orderBy('ID', 'desc')->value('StepId') == $stepId;
    }
    //处理办理人名称
    public static function getTransactors($userName = '', $task = '', $roleName = '', $organizationName = '',$mind='',$mindTitle='')
    {
        if (!$userName && !$task && !$roleName && !$organizationName && !$mind) {
            return '不限制';
        }
        $result = '';

        if ($userName) $result .= $userName . "-";
        if ($task) $result .= $task . "-";
        // if ($roleName) $result .= $roleName . ", ";
        if ($organizationName) $result .= $organizationName . "-";
        //之前有信息则不显示智能类型
        if(!$userName && !$task && !$roleName && !$organizationName&& $mind){
            if($mindTitle)$result .= $mindTitle . "-";
            $result .= $mind . "-";
        }
        $result = rtrim($result, "-");
        return $result;
    }
    //获取内容查找的数据
    public static function getQueryData($content): array
    {
        $selectFrom = 'input,select,textare';
        $selectRule = [
            'id' => [$selectFrom, 'id'],
            'title' => [$selectFrom, 'title'],
            'name' => [$selectFrom, 'name'],
            'value' => [$selectFrom, 'value'],
        ];
        $FormField = QueryList::html($content)->rules($selectRule)->query()->getData()->all();
        return $FormField ?: [];
    }

    public static function delTake($string){
        if(strripos($string,"(")||stripos($string,")")){
            $start=strripos($string,"(");
            $end=stripos($string,")")-$start+1;
    
            $str=substr($string,$start,$end);
            $estr=rtrim($str,")");
            $estr=(string)ltrim($estr,"(");
            $res="\$estr=$estr;";
            eval($res);
            if($estr===false){
                throw new \Exception('error');
            }
            $string=str_replace($str,$estr,$string);
            $string=self::delTake($string);
        }
        return $string;  
    }

   

    public static function  outCondition($to,$auditing,$flow,$request){

        if (isset($auditing->belongsToStep->OutCondition[$to])) { 
            $requestBack=array_except($request,['sso_token','center_token','AuditingId']);
            $FormFieldTemp=$flow->FormFieldTemp;
            //清除旧参数
            foreach($requestBack as $k=>$v){
                request()->offsetUnset($k);
            }

            foreach($FormFieldTemp as $k=>$v){
                request()->offsetSet($k, $v);
            }
            $request=\Request::all();
            oaLog($request,'进入转出条件处理',$flow->ID);

            // dd($FormFieldTemp);
            foreach($auditing->belongsToStep->OutCondition[$to] as $k=>$v){
                // dd($auditing->belongsToStep);
                switch($k){
                    case 'name':break;
                    case 'outCondition':    
                        oaLog($request,'基础逻辑判断【开启】',$flow->ID);    
                                
                        foreach($FormFieldTemp as $key=>$value){
                            if(is_array($value)){
                                $value=implode(',',$value);
                            }
                            $v=str_replace($key,"'".$value."'",$v);
                            //使用正则匹配
                                                    
                        }
                        if($v){
                            $encoding=mb_detect_encoding($v,array("ASCII",'UTF-8',"GB2312","GBK",'BIG5'));
                            // 如果字符串的编码格式不为UTF_8就转换编码格式
                            if ($encoding!='UTF-8') {
                                $v=mb_convert_encoding($v, 'UTF-8',$encoding);
                            }
                            //(截取防止注入)
                            try{
                                //只支持一级括号优先级
                                $v=FlowClass::delTake($v);
                                $res="\$res=$v;";
                                eval($res);
                                if($res===false){
                                    oaLog($request,'基础逻辑判断处理结果【不通过】',$flow->ID); 
                                    throw new \Exception;
                                }
                                oaLog($request,'基础逻辑判断处理结果【通过】',$flow->ID); 
                            }catch(\Throwable $t){
                                ob_start();
                                 var_dump($t->getMessage().PHP_EOL.$t->getLine().PHP_EOL.$t->getTraceAsString().PHP_EOL);

                                $cc=ob_get_clean();
                                $a=fopen(__DIR__.'/outCondition.log','w');
                                fwrite($a, $cc.PHP_EOL);
                                fclose($a);
                                oaLog($request,'基础逻辑判断处理结果【异常抛出】',$flow->ID); 
                                // dd($t);
                                //；错误提示 公式，计算公式：{$v}  
                                throw new OAException("表单字段基础验证不通过：".$auditing->belongsToStep->OutCondition[$to]['msg']);
                            }
                        }

                    break;
                    case 'formApi':
                            oaLog($request,'表单API校验【开启】',$flow->ID); 
                            foreach($v as $vv){
                                if($vv){
                                    try{
                                        $res=app('\App\Api\Controller\ApiController')->requestId($vv);
                                        if($res instanceof \Illuminate\Http\JsonResponse){
                                            $res=$res->original;
                                         }
                                        if(isset($res['code'])&&$res['code']==600)throw new OAException($res['data']);
                                        oaLog($request,"表单API校验【成功】API详情[ID-{$vv}-".hlyun_oa_api::find($vv)->Name."]",$flow->ID,$vv); 
                                    }catch(\Throwable $t){
                                        ob_start();
                                        var_dump($t->getMessage().PHP_EOL.$t->getLine().PHP_EOL.$t->getTraceAsString().PHP_EOL);
                                        $cc=ob_get_clean();
                                        $a=fopen(__DIR__.'/formApi.log','w');
                                        fwrite($a, $cc.PHP_EOL);
                                        fclose($a);
                                        oaLog($request,"表单API校验【异常抛出】API详情[ID-{$vv}-".hlyun_oa_api::find($vv)->Name."]",$flow->ID,$vv); 
                                        if($t instanceof OAException){
                                            throw new OAException($t->getMessage());
                                        }                                        
                                        throw new OAException("表单验证API编号：{$vv}《".hlyun_oa_api::find($vv)->Name."》存在异常，请联系开发人员解决");
                                    }
                                }
                            }
                        break;
                    case 'synApi':
                            oaLog($request,'同步操作API【开启】',$flow->ID);
                            foreach($v as $vv){
                                if($vv){
                                    try{
                                        $res=app('\App\Api\Controller\ApiController')->requestId($vv);
                                        if($res instanceof \Illuminate\Http\JsonResponse){
                                           $res=$res->original;
                                        }
                                       
                                        if(isset($res['code'])&&$res['code']==600)throw new OAException($res['data']);
                                        oaLog($request,"同步操作API【成功】API详情[ID-{$vv}-".hlyun_oa_api::find($vv)->Name."]",$flow->ID,$vv); 
                                    }catch(\Throwable $t){
                                        ob_start();
                                         var_dump($t->getMessage().PHP_EOL.$t->getLine().PHP_EOL.$t->getTraceAsString().PHP_EOL);
                                        $cc=ob_get_clean();
                                        $a=fopen(__DIR__.'/synApi.log','w');
                                        fwrite($a, $cc.PHP_EOL);
                                        fclose($a);
                                        oaLog($request,"同步操作API【异常抛出】API详情[ID-{$vv}-".hlyun_oa_api::find($vv)->Name."]",$flow->ID,$vv); 
                                        if($t instanceof OAException){
                                            throw new OAException($t->getMessage());
                                        }
                                        throw new OAException("同步操作API编号：{$vv}《".hlyun_oa_api::find($vv)->Name."》存在异常，请联系开发人员解决");
                                    }
                                }
                            }
                            
                        break;
                }
                
            }
            //移除参数
            foreach($FormFieldTemp as $k=>$v){
                request()->offsetUnset($k);
            }
            //清除旧参数
            foreach($requestBack as $k=>$v){
                request()->offsetSet($k,$v);
            }

        }
        return true;
    }

    public static function backCondition($auditing,$flow,$request){
        
        $requestBack=array_except($request,['sso_token','center_token','AuditingId']);
        $FormFieldTemp=$flow->FormFieldTemp;

        //清除旧参数
        foreach($requestBack as $k=>$v){
            request()->offsetUnset($k);
        }

        foreach($FormFieldTemp as $k=>$v){
            request()->offsetSet($k, $v);
        }

        $request=\Request::all();
        
        // dd($auditing->BackCondition);
        if(!empty($auditing->BackCondition)){
            oaLog($request,'回退操作API【开启】',$flow->ID);
        }else{
            return true;
        }
       
        // dd($FormFieldTemp);
        
        foreach($auditing->BackCondition as $v){
            try{
                $res=app('\App\Api\Controller\ApiController')->requestId($v);
                if($res instanceof \Illuminate\Http\JsonResponse){
                    $res=$res->original;
                }
                if(isset($res['code'])&&$res['code']==600)throw new OAException($res['data']);
                oaLog($request,"回退操作API【成功】API详情[ID-{$v}-".hlyun_oa_api::find($v)->Name."]",$flow->ID,$v); 
            }catch(\Throwable $t){

                ob_start();
                 var_dump($t->getMessage().PHP_EOL.$t->getLine().PHP_EOL.$t->getTraceAsString().PHP_EOL);
                $cc=ob_get_clean();
                $a=fopen(__DIR__.'/BackCondition.log','w');
                fwrite($a, $cc.PHP_EOL);
                fclose($a);

                oaLog($request,"回退操作API【异常抛出】API详情[ID-{$v}-".hlyun_oa_api::find($v)->Name."]",$flow->ID,$v); 
                if($t instanceof OAException){
                    throw new OAException($t->getMessage());
                }
                throw new OAException("回退操作API编号：{$v}《".hlyun_oa_api::find($v)->Name."》存在异常，请联系开发人员解决");
            }       
        }
        //移除参数
        foreach($FormFieldTemp as $k=>$v){
            request()->offsetUnset($k);
        }
        //清除旧参数
        foreach($requestBack as $k=>$v){
            request()->offsetSet($k,$v);
        }
        return true;
    }

    //自追加参数
    public static function  appendParam($AuditingId,array $params){
        $flow=hlyun_oa_process_flow::find(
            hlyun_oa_process_flow_auditing::find($AuditingId)->FlowId
        );
        $flow->FormField=array_merge($flow->FormField,$params);
        $flow->FormFieldTemp=array_merge($flow->FormFieldTemp,$params);
        $flow->save();
        return true;        
    }
       /**
     * 获取搜索字段 
     * @Author   Allen
     * @DateTime 2019-11-08
     * @param    [type]     $base [description]
     * @return   [type]           [description]
     */
    public static function getSearchFields($Modules){
 
        $data=[];
        $paramModules=[];
        foreach ($Modules as $key => $Module) {
            if (!empty(session('getSearchFields'.$Module))) {
                $data[$Module]=session('getSearchFields'.$Module);
            }else{
                $paramModules[]=$Module;
            }
        }

        if (empty($paramModules) ) {
            return $data;
        }else{
            $request=\Request::all();
            $params['sso_token']=$request['sso_token'];
            $params['Modules']=$paramModules;
            $curlRequest=['method'=>'GET','params'=>$params,'route'=>'/api/usersearchfields'];
            $result=HelperClass::curl($curlRequest);
            $res=json_decode($result,true);
            // dd($res);
            // echo "<pre>";
            // var_dump($res,$result);exit;
            if (isset($res['code'])&&$res['code']==0) {
                $resdata=$res['data'];
                foreach ($resdata as $key => &$value) {
                    sort($value);
                    foreach ($value as $k => &$v) {
                            sort($v['fields']);
                        if ($v['type']==4) {
                            $userinfo=HelperClass::getUserInfo($request['sso_token']);
                            $AccreditIds=self::getAccreditIdsArray($userinfo['AccreditIds']);
                          
                            foreach ($v['fields'] as $k1 => &$v1) {
                                $v1['options']=$AccreditIds;
                            }
                        }
                    }
                }
                foreach ($resdata as $key => $value) {
                    $data[$key]=$value;
                    session(['getSearchFields'.$key=>$value]);
                }
                return $data;
            }else{
                return $result;
            }
        }
    }
    public static function getAccreditIdsArray($AccreditIds){
         // $user = HelperClass::getUserInfo($request['sso_token']);
         // $AccreditIds = $user['AccreditIds'];
         if (session('AccreditIdsArray')) {
             $AccreditIds = session('AccreditIdsArray');
         }else{
             $params['sso_token']=sso_token();
             $params['sql']="hlyun_sso_organizations.AccreditId in (".implode(',',$AccreditIds).") and OrganizationType in (1,2)";//增加OrganizationType条件筛选，不然会出现多个操作部
             $params['skip']=0;
             $params['pageSize']=10000;
             $params['select']=['hlyun_sso_organizations.ID','hlyun_sso_organizations.AccreditId','hlyun_sso_organizations.Name'];
             $curlRequest=['method'=>'GET','params'=>$params,'route'=>'/api/companies'];
             $res=HelperClass::curl($curlRequest);
             $res=json_decode($res,true);
             if (isset($res['code'])&&$res['code']==0) {
                 $AccreditIds = array_column($res['data'],'Name','AccreditId') ;
                 session(['AccreditIdsArray'=>$AccreditIds]);
             }else{
                 return response()->json(['code'=>600,'data'=>'获取获取所属企业信息失败']);
             }
         }
         return $AccreditIds;
     }
     /**
 *  搜索条件
 * @Author   Allen
 * @DateTime 2019-12-10
 * @param    [type]     $request     [description]
 * @param    [type]     $module      [description]
 * @param    array      $AccreditIds [description]
 * @return   [type]                  [description]
 */
    public static function getConditions($request,$module,$AccreditIds=[])
    {
        $userSearchFields = self::getSearchFields([$module]);
        $searchFieldList = $userSearchFields[$module];
        $sql                = '';
        $dataRequest        = array_except($request, ['status', 'dateType', 'startDate', 'endDate', 'spage', 'page', 'pageType', 'dateSelect', 'sso_token', 'typePrice','statusType']);
       //模糊搜索：文本输入字段
        $searchFieldLike=[];
        foreach ($searchFieldList as $key => $searchField) {
            $Field=collect($searchField['fields'])->where('ClassAttribute',1)->all();
            $searchFieldLike=array_merge($searchFieldLike, $Field);
        }
        $likeField=collect($searchFieldLike)->unique('name')->pluck('name')->toArray();
        foreach ($likeField as $key => $value) {
            $value=mb_substr($value, 0,mb_strlen($value)-2);
            $likeField[$key]=str_replace('/', '.', $value) ;
        }
       //全等搜索：下拉选项字段
        $searchFieldequation=[];
        foreach ($searchFieldList as $key => $searchField) {
            $Field=collect($searchField['fields'])->where('ClassAttribute',3)->all();
            $searchFieldequation=array_merge($searchFieldequation, $Field);
        }
        $equationField=collect($searchFieldequation)->unique('name')->pluck('name')->toArray();
        foreach ($equationField as $key => $value) {
            $value=mb_substr($value, 0,mb_strlen($value)-2);
            $equationField[$key]=str_replace('/', '.', $value) ;
        }
        // 日期搜索：日期区间字段
        $searchFielddate=[];
        foreach ($searchFieldList as $key => $searchField) {
            $Field=collect($searchField['fields'])->where('ClassAttribute',2)->all();
            $searchFielddate=array_merge($searchFielddate, $Field);
        }
        
        $dateField=collect($searchFielddate)->unique('name')->pluck('name')->toArray();
        foreach ($dateField as $key => $value) {
            $dateField[$key]=str_replace('/', '.', $value).'-startDate';
        }
        // 多选搜索：所属企业字段
        $searchFieldAccredit=[];
        foreach ($searchFieldList as $key => $searchField) {
            $Field=collect($searchField['fields'])->where('ClassAttribute',4)->all();
            $searchFieldAccredit=array_merge($searchFieldAccredit, $Field);
        }
        $AccreditField=collect($searchFieldAccredit)->unique('name')->pluck('name')->toArray();
        foreach ($AccreditField as $key => $value) {
            $value=mb_substr($value, 0,mb_strlen($value)-2);
            $AccreditField[$key]=str_replace('/', '.', $value) ;
        }
      
        foreach ($dataRequest as $key => $value) {
            if (empty($value) && $value != 0) {
                unset($dataRequest[$key]);
            }
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    if (empty($v)&& $v != '0') {
                        unset($dataRequest[$key][$k]);
                        unset($value[$k]);
                    }
                }
                if (empty($value)) {
                    unset($dataRequest[$key]);
                }
            }
            if (strstr($key, '/')&&isset($dataRequest[$key])) {
                $dataRequest[str_replace('/', '.', $key)] = $value;
                unset($dataRequest[$key]);
                // dd(str_replace('/','.',$key));
            }
        }
        // dd($dataRequest);
        foreach ($dataRequest as $k => $v) {
            if (in_array($k, $likeField)||in_array($k, $equationField)||in_array($k, $dateField)||in_array($k, $AccreditField)) {
                if (in_array($k, $AccreditField)) {
                        $sql .= " and AccreditId in (" . implode(',', $v) . ")";
                }else{
                    $FieldSql = " and (";
                    $sqlArr       = [];
                    if (is_array($v)) {
                        foreach ($v as $v1) {
                            // var_dump($v1);exit;
                            $v1Arr = explode(',', $v1);//前端会用逗号分隔搜索多个
                            foreach ($v1Arr as $v2) {
                                    if (in_array($k, $equationField)) {
                                         $sqlArr[] = "$k = $v2";
                                    }
                                    if (in_array($k, $likeField)) {
                                        $v2=trim($v2);
                                        if (!empty($v2)) {
                                            $sqlArr[] = "$k LIKE '%{$v2}%'";
                                        }
                                    }
                                    if (in_array($k, $dateField)) {
                                        $dateKeyArr = explode('-', $k);
                                        $dateKey    = $dateKeyArr[0];
                                        $startDate  = $v2 . ' 00:00:00';
                                        $endDate    = isset($dataRequest[$dateKey . '-endDate'])&&!empty(array_first($dataRequest[$dateKey . '-endDate'])) ? array_first($dataRequest[$dateKey . '-endDate']). ' 23:59:59' : '2999-01-01';
                                        $sqlArr[] = " {$dateKey} between '{$startDate}' and '{$endDate}'";
                                    }
                            }
                        }
                    }
                    if (!empty($sqlArr)) {
                        $sqlArr = implode(' or ', $sqlArr);
                        $sql    .= $FieldSql . $sqlArr . ") ";
                    }
                }
                
            }
        }
       // if (!strstr($sql, 'AccreditId in')) {
       //      if (!empty($AccreditIds)) {
       //          $sql .= " and AccreditId in (" . implode(',', $AccreditIds) . ")";
       //      }else{
       //          $userInfo=HelperClass::getUserInfo($request['sso_token']);
       //          $sql .= " and AccreditId in (" . implode(',', $userInfo['AccreditIds']) . ")";
       //      }
       //  }
        $sql =strstr($sql,'and')?substr($sql, strpos($sql, 'and')+3):$sql;
        return $sql;
    }

    public static function createUniqueNumber (){
        return date('Ymdhis'). str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
    }

     /**
     * @Descripttion: 判断是否属于wp订单业务 cxc指导的
     * @param {type} 
     * @Date: 2020-03-02 17:06:06
     * @Author: czm
     * @return: boolen
     */
    public static function isWp($workNumber){
        return strpos($workNumber,"WP_")!==false&&hlyun_order_orders_wp::where('workNumber',$workNumber)->value('OrderSource')==6;
    }
}