<?php

namespace App\Http\Controllers;

use App\Http\ModuleClass\HelperClass;
use function GuzzleHttp\json_encode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use QL\QueryList;
use Validator;
use App\Models\hlyun_oa_api;
use App\Rules\checkNameUnique;
use App\Http\ModuleClass\SsoClass;
use App\Models\hlyun_oa_process;
use App\Models\hlyun_oa_process_flow;

class ApiController extends Controller
{
    public function apiIndex(){
        return view('api.apiIndex');
    }

    public function apiCategory(){
        return view('api.category');
    }

    public function apiList(Request $request){
        $sql=hlyun_oa_api::where('ID','!=','');
        if($request->Type!='all'){
            $sql=$sql->Type($request->Type);
        }
        $request=$request->all();
        $page = $this->setPage($request);

        if(isset($request['ID'])&&$request['ID']!=null){
            $sql=$sql->where("ID",$request['ID']);
        }

        if(isset($request['Name'])&&$request['Name']!=null){
            $sql=$sql->Name($request['Name']);
        }
        if(isset($request['Author'])&&$request['Author']!=null){
            $sql=$sql->Author($request['Author']);
        }
        if(isset($request['Route'])&&$request['Route']!=null){
            $sql=$sql->Route($request['Route']);
        }

        if(isset($request['Controller'])&&$request['Controller']!=null){
            $sql=$sql->Controller($request['Controller']);
        }

        $data=$sql->skip($page['skip'])->limit($page['pageSize'])->get()->toArray();
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

    

    public function addApiOpen(){
        return ['code'=>0,'data'=>'开启'];
    }

    public function addApiSub(Request $request){
        $validator = Validator::make($request->all(), [
            'CategoryType' => 'required',
            'Name' => ['required',new checkNameUnique('hlyun_oa_api')],
            'Route' => 'required',
            'Controller' => 'required',
            'Author' => 'required',
        ], [
            'CategoryType.required' => '分类不能为空',
            'Name.required' => '名称不能为空',
            'Route.required' => '路由不能为空',
            'Controller.required' => '控制器方法不能为空',
            'Author.required' => '请明确作者',
        ]);

        if ($validator->fails()) {
            $error = $validator->errors()->all();
            return ['code' => 600, 'data' => $error];
        }
        return hlyun_oa_api::api($request) === true ?
        ['code' => 0, 'data' => '成功！'] : ['code' => 600, 'data' => '失败!存在异常'];
    }

    public function editApiOpen(Request $request){
        // 获取编辑信息
        $info = hlyun_oa_api::where('ID', $request->id)->first()->toArray();

        return ['code' => 0, 'info' => $info];
    }

    public function editApiSub(Request $request){
        return $this->addApiSub($request);
    }

    public function getAllOrganizations(Request $request)
    {
        // dd(SsoClass::getAllOrganizations());
        return SsoClass::getAllOrganizations();
    }

    public function getDepartmentUsers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
           
        ], [
            'id.required' => '企业ID不能为空',
        ]);

        if ($validator->fails()) {
            $error = $validator->errors()->all();
            return ['code' => 600, 'data' => $error];
        }
        return SsoClass::getDepartmentUsers($request->id);
    }

    public function getOrganizationRoles(Request $request)
    {   
       
        $validator = Validator::make($request->all(), [
            'id' => 'required',
           
        ], [
            'id.required' => '企业ID不能为空',
        ]);

        if ($validator->fails()) {
            $error = $validator->errors()->all();
            return ['code' => 600, 'data' => $error];
        }
        return SsoClass::getOrganizationRoles($request->id);
    }

    public function getTasks(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
           
        ], [
            'id.required' => '企业ID不能为空',
        ]);

        if ($validator->fails()) {
            $error = $validator->errors()->all();
            return ['code' => 600, 'data' => $error];
        }
        return SsoClass::getTasks($request->id);
    }

    public function delApiSub(Request $request)
    {
        hlyun_oa_api::find($request['id'])->delete();
        return ['code'=>0,'data'=>'删除成功'];
    }
    
    //设置页码
    public function setPage($get)
    {
       
        $page = isset($get['page']) ? $get['page'] : 1;
        $pageSize = isset($get['spage']) ? $get['spage'] : 10;
        $skip = ($page - 1) * $pageSize;
        return ['pageSize' => $pageSize, 'skip' => $skip, 'page' => $page];
    }
    

}
