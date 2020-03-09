<?php

namespace App\Http\Controllers;

use Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\hlyun_oa_process_category;
use App\Models\hlyun_oa_form;
use App\Models\hlyun_oa_process;
use App\Rules\checkNameUnique;
use App\Models\hlyun_oa_process_step;
use function GuzzleHttp\json_decode;
use function GuzzleHttp\json_encode;
use App\Models\hlyun_oa_api;
use App\Models\hlyun_oa_index_process_menu;
use App\Http\ModuleClass\HelperClass;
/*
 * 定义流程配置管理
 */
class ProcessController extends Controller
{
    public function workflowIndex(){

        //分类变量
        $category=hlyun_oa_process_category::select('Name', 'ID')->where('Status',1)->get()->toArray();
        $form=hlyun_oa_form::select('Name', 'ID')->where('Status',1)->get()->toArray();
        return view('process.workflowIndex',['category'=>$category,'form'=>$form]);
    }
    
    /**
     * TODO: 返回新增流程基础信息
     * @param $request
     * @return array
     * @author czm
     * 2018/11/21 15:18
     */
    public function addWorkflowOpen(\Request $request)
    {
        $data=[];
        $form = hlyun_oa_form::select('ID', 'Name')->get()->toArray();    
        $data['Category']=hlyun_oa_process_category::where('Status',1)->get(['ID','Name'])->toArray();
        $data['form']=$form;
        return ['code' => 0, 'data' => $data];
    }

    public function addWorkflowSub(\Request $request){
        $request=$request::all();
        $validator = Validator::make($request, [
            'Name' => ['required',new checkNameUnique('hlyun_oa_process')],
        ], [
            'Name.required' => '流程名称不能为空',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors()->all()[0];
            return response()->json(['code' => 600, 'data' => $errors]);
        }
        // 转发处理 获取节点人员
        try{
            hlyun_oa_process::process($request);
            return ['code' => 0, 'data' => '保存成功!'];
        }catch(\throwable $t){
            var_dump($t->getTraceAsString());exit;
            return ['code' => 600, 'data' => '存在异常','msg'=>$t->getTraceAsString()];
        }
       
    }

    public function workflowList(\Request $request)
    {
        // dd(hlyun_oa_process::selectRaw('group_concat(distinct(Name) separator "+") as SKT')->pluck('SKT')->toArray());

        $request=$request::all();
        $page = $this->setPage($request);
        $sql=hlyun_oa_process::where('ID','!=','');
        // var_dump(($request['Status']));exit;
        if($request['Status']!=null){
            $sql=$sql->Status($request['Status']);
        }
        if($request['Name']!=null){
            $sql=$sql->Name($request['Name']);
        }
        if($request['CategoryId']!=null){
            $sql=$sql->CategoryId($request['CategoryId']);
        }
        if($request['FormId']!=null){
            $sql=$sql->FormId($request['FormId']);
        }

        $data=$sql->with(['hasManyCate'=>function($query){
            $query->select('Name');
        }])->with(['hasManyForm'=>function($query){
            $query->select('Name');
        }])->skip($page['skip'])->limit($page['pageSize'])->OrderBy('UpdatedAt','desc')->get()
        ->map(function($item, $key){
            $item=$item->toArray();
            $item['CategoryName']=$item['has_many_cate'][0]['Name'];
            $item['FormName']=$item['has_many_form'][0]['Name'];
            unset($item['has_many_cate']);
            unset($item['has_many_form']);
            return $item;
        })->toArray();
        $count=$sql->count();
        $pageCount = ceil($count / $page['pageSize']);
        if ($count == 0) {
            $startCount = $page['skip'];
        } else {
            $startCount = $page['skip'] + 1;
        }
        $endCount = $page['skip'] + count($data);
        return ['code' => 0, 'data' => $data, 'pageCount' => $pageCount, 'count' => $count, 'startCount' => $startCount, 'endCount' => $endCount];
    }

    public function delWorkflowSub(\Request $request)
    {
        $request=$request::all();
  
        hlyun_oa_process::where('ID', $request['id'])->delete();
        return ['code' => 0, 'data' => '删除成功'];
    }

    //编辑表单开启
    public function editWorkflowOpen(\Request $request){
        $request=$request::all();
        $data = hlyun_oa_process::where('ID', $request['id'])->with(['hasManyCate'=>function($query){
            $query->select('Name','ID');
        }])->with(['hasManyForm'=>function($query){
            $query->select('Name','ID');
        }])->first()->toArray();

        $data['CategoryName']=$data['has_many_cate'][0]['Name'];
        $data['FormName']=$data['has_many_form'][0]['Name'];

        $data['CategoryId']=$data['has_many_cate'][0]['ID'];
        $data['FormId']=$data['has_many_form'][0]['ID'];
        unset($data['has_many_cate']);
        unset($data['has_many_form']);
     
        return ['code' => 0,'data' => $data];
    }
    //步骤开启

    public function setStepOpen(\Request $request){
        $data=$this->editWorkflowOpen($request)['data'];
        $request=$request::all();
        $step=hlyun_oa_process_step::where('ProcessId',$request['id'])->get();
        $step=$step->map(function($item, $key){
            //单位换算px 样式整理
            $item=$item->toArray();
            foreach($item['Style'] as $k=>&$v)
            {
                switch ($k){
                    case 'width':
                        $v='width:'.$v."px";
                        break;
                    case 'height':
                        // $item['Style']['line-height']='line-height:'.$v."px";
                        $v='height:'.$v."px";
                        break;
                    case 'color':
                        $v='color:'.$v;
                        break;
                    default:break;
                    
                }
            }
            $item['Style']['left']= 'left:'.$item['SetLeft']."px";
            $item['Style']['top']= 'top:'.$item['SetTop']."px";
            $item['Style']=implode(";",$item['Style']);
            return $item;
        })->toArray();
        $processData=json_encode(['total'=>count($step),'list'=>$step]);
        // dump($processData);exit;
        // dd(json_encode($step));
        return view('step.setStep',['data'=>$data,'processData'=>$processData]);
    }

    public function setStepSub(\Request $request){
        $request=$request::all();
        $validator = Validator::make($request, [
            'ProcessId' =>'required',
            'processInfo'=>'required',
        ], [
            'ProcessId.required' => '流程ID不能为空',
            'processInfo.required' => '流程步骤信息不能为空',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors()->all()[0];
            return response()->json(['code' => 600, 'data' => $errors]);
        }
        //验证是否存在该流程与开放
        $process=hlyun_oa_process::where('ID',$request['ProcessId'])->count();
        if(!$process){
            return ['code'=>600,'data'=>'流程不存在或被禁用'];
        }
        $processInfo=json_decode($request['processInfo'],true);
        //解析提交过来的
        DB::beginTransaction();
        try{
            hlyun_oa_process_step::whereNotIn('ID',array_keys($processInfo))->where('ProcessId',$request['ProcessId'])->delete();
            foreach($processInfo as $k=>$v){
                hlyun_oa_process_step::find($k)->update([
                    'SetLeft'=>$v['left'],
                    'SetTop'=>$v['top'],
                    'To'=>implode(",",array_unique($v['To']))
                ]);
            }
            DB::commit();
            return ['code'=>0,'data'=>'保存成功'];

        }catch(\Exception $e){
      
            DB::rollBack();
            return ['code'=>600,'data'=>'存在异常','msg'=>$e->getTraceAsString()];
        }
    }

    public function setStepCheck(\Request $request)
    {
        $request=$request::all();
        $validator = Validator::make($request, [
            'ProcessId' =>'required',
        ], [
            'ProcessId.required' => '流程ID不能为空',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors()->all()[0];
            return response()->json(['code' => 600, 'data' => $errors]);
        }
        //检查是否存在步骤一的设定
        $first=hlyun_oa_process_step::where('ProcessId',$request['ProcessId'])->where('Type',2)->count();
        if($first){
            return ['code'=>0,'data'=>'步骤1存在'];
        }else{
            return ['code'=>600,'data'=>'步骤1不存在'];
        }
    }

    public function setStepAdd(\Request $request){
        $request=$request::all();
        $validator = Validator::make($request, [
            'ProcessId' =>'required',
        ], [
            'ProcessId.required' => '流程ID不能为空',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors()->all()[0];
            return response()->json(['code' => 600, 'data' => $errors]);
        }
        //验证是否存在该流程与开放
        $process=hlyun_oa_process::where('ID',$request['ProcessId'])->count();
        if(!$process){
            return ['code'=>600,'data'=>'流程不存在或被禁用'];
        }
        $step=hlyun_oa_process_step::create([ 
            'Name'=>'新建步骤',
            'To'=>isset($request['To'])?implode(",",$request['To']):"",
            'Icon'=>'icon-play',
            'Style'=>['width'=>160,'height'=>40,'color'=>'#0e76a8'],
            'SetLeft'=>$request['left'],
            'SetTop'=>$request['top'],
            'ProcessId'=>$request['ProcessId'], 
        ]);
        // dd($step);
        if($step){
            return [
                'code'=>0,
                'data'=>[
                    'ID'=>$step->ID,
                    'ProcessId'=>$step->ProcessId, 
                    'Name'=>'新建步骤',
                    'To'=>$step->Icon,
                    'Icon'=>$step->Icon,//图标
                    'Style'=>'left:'.$step->SetLeft.'px;top:'.$step->SetTop.'px;width:160px;height:40px;color:#0e76a8;'//样式 
                    ]
                ];
        }else{
            return ['code'=>600,'data'=>'新增失败'];
        }

       
    }

    
    //删除
    public function setStepDel(\Request $request){
        $request=$request::all();
        if(isset($request['ProcessId'])&&$request['ProcessId']){
            hlyun_oa_process_step::where("ProcessId",$request['ProcessId'])->delete();
        }else{
            hlyun_oa_process_step::find($request['ID'])->delete();
        }
        return ['code'=>0,'data'=>"删除成功"];
    }
    //属性修改提交
    public function setStepEdit(\Request $request)
    {
        $request=$request::all();
        $data=hlyun_oa_process_step::find($request['ID'])->toArray();
        $to=explode(',',$data['To']);
        $exports=hlyun_oa_process_step::select('ID','Name')->where('ProcessId',$data['ProcessId'])->whereIn('ID',$to)->get()->toArray();
        $imports=hlyun_oa_process_step::select('ID','Name')->where('ProcessId',$data['ProcessId'])->whereNotIn('ID',[$request['ID']])->get()->toArray();
        //子流程列表
        $chlidList=hlyun_oa_process::Chlid()->where('Status',1)->get();
        return ['code'=>0,'data'=>$data,'exports'=>$exports,'imports'=>$imports,'chlidList'=>$chlidList];
    }
    //字段控制开启
    public function stepFieldEdit(Request $request)
    {   
        // [
        //     'title'=>'',
        //     'name'=>'',
        //     'value'=>'',
        //     'api'=>'',
        // ];
        $request=$request->all();
        $data=hlyun_oa_process_step::with(['hasManyForm'=>function($query){
            $query->select('Name','Field');
        }])->find($request['ID'])->toArray();
        // dd($data);
        $va['StepField']=$data['Field'];
        $va['InitDataApi']=$data['InitDataApi'];
        $va['FormField']=$data['has_many_form'][0]['Field'];
        $va['FormName']=$data['has_many_form'][0]['Name'];
        $data=$va;
        //获取请求类api列表
        $api=hlyun_oa_api::select('ID','Name','Description')->Type('get')->where('Status',1)->get();
        return ['code'=>0,'data'=>$data,'api'=>$api];
    }
    public function formFieldSub(Request $request){
        $request=$request->all();
        $field=array('name','title','value','api');
        if(!isset($request['Field'])){
            $result='';
        }else{
            $data=$request['Field'];
            foreach($field as $fieldname){
                $inkey=array_keys($data[$fieldname]);
                $outkey=array_keys($data);
                $result=array();
                foreach($inkey as $val){
                    foreach($outkey as $value){
                        $result[$val][$value]=$data[$value][$val];
                    }
                }
            }
        }
        hlyun_oa_process_step::find($request['ID'])->update([
            'Field'=>$result,
            'InitDataApi'=>$request['InitDataApi']??''
        ]);

        return ['code'=>0,'data'=>'编辑成功'];
    }

    //属性提交修改
    public function attributeSub(\Request $request)
    {
        $request=$request::all();
        hlyun_oa_process_step::find($request['ID'])->update([
            'Name'=>$request['Name'],
            'To'=>isset($request['To'])?implode(",",$request['To']):"",
            'Type'=>$request['Type'],
            'Countersign'=>$request['Countersign'],
            'BackType'=>$request['BackType'],
            // 'HandleType'=>$request['HandleType'],
            // 'BackType'=>$request['BackType'],
            'DataLock'=>$request['DataLock'],
            'Icon'=>$request['Icon'],
            'Style'=>$request['Style'],
        ]);
        return ['code'=>0,'data'=>'编辑成功!'];
    }

    //转出条件查询
    public function outCondition(Request $request)
    {
        $request=$request->all();
        $data=hlyun_oa_process_step::with(['hasManyForm'=>function($query){
            $query->select('Name','Field');
        }])->find($request['ID'])->toArray();
       
        //组装步骤情况
        $outCondition=[];
        // dd($data['To']);
        if(!$data['To']){
            return ['code'=>600,'data'=>'该节点不具备流转条件'];
        }
        foreach(explode(',',$data['To']) as $k=>$v)
        {
            $outCondition[$k]['name']="$data[ID]-$v";
            $outCondition[$k]['title']="$data[Name]->".hlyun_oa_process_step::find($v)->Name;
        }
        $formApi=hlyun_oa_api::select('ID','Name','Description')->Type('form')->where('Status',1)->get();
        $synApi=hlyun_oa_api::select('ID','Name','Description')->Type('syn')->where('Status',1)->get();
        $va['OutCondition']=$data['OutCondition'];
        $va['FormField']=$data['has_many_form'][0]['Field'];
        $va['FormName']=$data['has_many_form'][0]['Name'];
        $data=$va;
        return ['code'=>0,'data'=>$data,'formApi'=>$formApi,'synApi'=>$synApi,'outCondition'=>$outCondition];
    }
    //转出控制开启
    public function outConditionSub(Request $request)
    {
        $request=$request->all();

        $result=$request['formData'];
        if(!empty($result)){
            $result=collect($result)->keyBy("name")->toArray();
        }
        hlyun_oa_process_step::find($request['ID'])->update([
            'OutCondition'=>$result
        ]);
        return ['code'=>0,'data'=>'更新成功'];
    }

    //回退条件查询
    public function backCondition(Request $request)
    {
        $request=$request->all();
        $data=hlyun_oa_process_step::with(['hasManyForm'=>function($query){
            $query->select('Name','Field');
        }])->find($request['ID'])->toArray();
        $synApi=hlyun_oa_api::select('ID','Name','Description')->Type('syn')->where('Status',1)->get();
        return ['code'=>0,'data'=>$data,'synApi'=>$synApi];
    }
    //转出控制开启
    public function backConditionSub(Request $request)
    {
        $request=$request->all();

        $result=$request['BackCondition']??[];

        hlyun_oa_process_step::find($request['ID'])->update([
            'BackCondition'=>$result
        ]);
        return ['code'=>0,'data'=>'更新成功'];
    }

    //办理人员配置
    public function user(Request $request){
        $request=$request->all();
        $data=hlyun_oa_process_step::find($request['ID'])->Transactors;

        $field=hlyun_oa_process_step::with(['hasManyForm'=>function($query){
            $query->select('Name','Field');
        }])->find($request['ID'])->toArray();
        $field=$field['has_many_form'][0]['Field'];
        // dd($data);
        return ['code'=>0,'data'=>$data,'field'=>$field];
    }

    public function userSub(Request $request){
        $request=$request->all();
        $validator = Validator::make($request, [
            'ID' =>'required',
            'type' =>'required',
        ], [
            'ID.required' => '流程ID不能为空',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors()->all()[0];
            return response()->json(['code' => 600, 'data' => $errors]);
        }
        $transactors=hlyun_oa_process_step::find($request['ID']);
        $transactors->OrganizationId='';
        $transactors->OrganizationName='';
        $transactors->Task='';
        $transactors->TaskId='';
        $transactors->RoleId='';
        $transactors->RoleName='';
        $transactors->UserName='';
        $transactors->UserId='';
        $transactors->Mind='';
        $transactors->MindId='';
        $transactors->MindType='';
        $transactors->MindTitle='';
        $data=$request['data']??'';
        //办理人员类型转换
        switch($request['type']){
            case '6':
                //智能获取
                $transactors->HandleType=6;
                $transactors->Mind=$data['mind'];
                $transactors->MindId=$data['mindId'];
                $transactors->MindType=$data['mindType'];
                $transactors->MindTitle=$data['mindTitle'];
                $transactors->MindSource=$data['mindSource'];
            break;
            case '5':
                $transactors->HandleType=5;
            break;
            case '4':
                //绑定组织
                $OrganizationName=[];
                $OrganizationId=[];
                foreach($data as $k=>$v){
                    $aname=$this->getFirstStr($v,'/');;
                    $aid=$this->getLastStr($v,'/');
                    $OrganizationName[$k]=$aname;
                    $OrganizationId[$k]=$aid;
                }
                // dd($UserName,$UserId);
                $transactors->OrganizationId=$OrganizationId;
                $transactors->OrganizationName=implode(',',$OrganizationName);
                $transactors->HandleType=4;
            break;
            case '3':
                //绑定角色 当前企业角色才可以操作
                $Task=[];
                $TaskId=[];
                foreach($data as $k=>$v){
                    $aname=$this->getFirstStr($v[0],'/');;
                    $aid=$this->getLastStr($v[0],'/');
                    if($aid==0){
                        $Task[$k]="当前订单业务企业/职务《".$v[1]."》";
                    }else{
                        $Task[$k]="当前申请人企业/职务《".$v[1]."》";
                    }
                    $TaskId[]=$aid.'/'.$v[1];
                }
                // dd($UserName,$UserId);
                $transactors->Task=implode(',',$Task);
                $transactors->TaskId=array_unique($TaskId);
                $transactors->HandleType=3;
            break;
            case '2':
                try{
                $RoleId=[];
                $RoleName=[];
                foreach($data as $k=>$v){
                    $aname=$this->getFirstStr($v[0],'/');;
                    // $aid=$this->getLastStr($v[0],'/');
                    $name=$this->getFirstStr($v[1],'/');
                    $id=$this->getLastStr($v[1],'/');
                    $RoleName[$k]=$aname.'/'.$name;
                    $RoleId[$k]=$id;
                }
                // dd($UserName,$UserId);
                $transactors->RoleId=$RoleId;
                $transactors->RoleName=implode(',',$RoleName);
                $transactors->HandleType=2;
                }catch(\Throwable $t){
                    return ['code'=>600,'data'=>'存在不合符参数.请勿勾选智能匹配'];
                }
            break;
            case '1':
             try{
                $UserId=[];
                $UserName=[];
                foreach($data as $k=>$v){
                    // $aname=$this->getFirstStr($v[0],'/');;
                    // $aid=$this->getLastStr($v[0],'/');
                    $name=$this->getFirstStr($v[1],'/');
                    $id=$this->getLastStr($v[1],'/');
                    // $transactors->UserName=;
                    $UserName[$k]=$name;
                    $UserId[$k]=$id;
                }
                $transactors->UserName=implode(',',$UserName);
                $transactors->UserId=$UserId;
                //指定人员
                $transactors->HandleType=1;
                }catch(\Throwable $t){
                    return ['code'=>600,'data'=>'存在不合符参数.请勿勾选智能匹配'];
                }
            break;
        }
        unset($request['center_token']);
        $transactors->Transactors=$request;
        $transactors->save();
        return ['code'=>0,'data'=>'更新成功'];
    }
    //js联动配置
    public function setting(Request $request){
        $request=$request->all();
        $data=hlyun_oa_process_step::with(['hasManyForm'=>function($query){
            $query->select('Name','Field','ObjId');
        }])->find($request['ID'])->toArray();
       
        $formApi=hlyun_oa_api::select('ID','Name','Description')->Type('get')->where('Status',1)->get();
        $va['Setting']=$data['Setting'];
        $va['FormField']=$data['has_many_form'][0]['Field'];
        $va['ObjId']=$data['has_many_form'][0]['ObjId'];
        $va['FormName']=$data['has_many_form'][0]['Name'];
        $data=$va;
        return ['code'=>0,'data'=>$data,'formApi'=>$formApi];
    }

    public function settingSub(Request $request){
        $request=$request->all();
        $result=$request['formData']??[];
  
        if(!empty($result)){
            // $result=collect($result)->keyBy("objId")->toArray();
            $result=collect($result)->toArray();

        }
        // dd($result);
        hlyun_oa_process_step::find($request['ID'])->update([
            'Setting'=>$result
        ]);
        return ['code'=>0,'data'=>'更新成功'];
    }

    public function getStepField(Request $request){
        $request=$request->all();
        $data=hlyun_oa_process_step::with(['hasManyForm'=>function($query){
            $query->select('Name','Field','ObjId');
        }])->find($request['ID'])->toArray();
       
        $formApi=hlyun_oa_api::select('ID','Name','Description')->Type('get')->where('Status',1)->get();
        $va['Setting']=$data['Setting'];
        $va['FormField']=$data['has_many_form'][0]['Field'];
        $va['ObjId']=$data['has_many_form'][0]['ObjId'];
        $va['FormName']=$data['has_many_form'][0]['Name'];
        $data=$va;
        return ['code'=>0,'data'=>$data,'formApi'=>$formApi];
    }
    public function roleWorkflowOpen()
    {
        $request=\Request::all();
        $menus=hlyun_oa_index_process_menu::where('ProcessId',$request['id'])->pluck('MenuId');
        $params['RequestData']=['menumoudle'=>1];
        $curlRequest=['method'=>'GET','params'=>$params,'route'=>'/api/base'];
        // dd(HelperClass::curl($curlRequest));
        $data = json_decode(HelperClass::curl($curlRequest), true);
        // dd($data);
        if(isset($data['code'])&&$data['code']==0){
            $t=function($items){
                $tree=[];
                foreach($items as $item){
                    if(isset($items[$item['Pid']])){
                        $items[$item['Pid']]['child'][]=&$items[$item['ID']];
                    }else{
                        $tree[]=&$items[$item['ID']];
                    }
                }
                return $tree;
            };
            $data=$t($data['data']['menumoudle']);
        }else{
            return ['code'=>600,'data'=>'权限列表接口获取错误'];
        }
     
        return ['code'=>0,'data'=>$data,'menus'=>$menus];
    }

    public function roleWorkflowSub(){
        $request=\Request::all();
        hlyun_oa_index_process_menu::where('ProcessId',$request['id'])->delete();
        if(is_array($request['menuId'])){

       
            foreach($request['menuId'] as &$v){
                $v=[
                    'ProcessId'=>$request['id'],
                    'MenuId'=>$v
                ];
            }
            $request['menuId']&&hlyun_oa_index_process_menu::insert($request['menuId']);
    }
        return ['code'=>0,'data'=>'编辑成功'];
        // dd($request['menuId']);
    }

    //设置页码
    public function setPage($get)
    {
        $page = isset($get['page']) ? $get['page'] : 1;
        $pageSize = isset($get['spage']) ? $get['spage'] : 10;
        $skip = ($page - 1) * $pageSize;
        return ['pageSize' => $pageSize, 'skip' => $skip, 'page' => $page];
    }



    public function getFirstStr($string,$limit){
        return substr($string,0,strrpos($string,$limit));
    }

    public function getLastStr($string,$limit){
        return substr(strrchr($string,$limit),1);
    }
}