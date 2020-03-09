<?php

namespace App\Http\Controllers;

use Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\hlyun_oa_process_category;
/**
 * @Description: 流程分类管理
 * @Param: 
 * @Author: czm
 * @return: 
 * @Date: 2019-07-01 17:29:09
 */

class CategoryController extends Controller
{
    
    
    public function CategoryIndex(\Request $request){
        return view('category.categoryManage');
    }
    /**
     *
     * 返回工作流数据
     *
     * @return json数据
     */
    public function CategoryList(){
        $request=\Request::all();
        foreach ($request as $k => $v) {
            if ($v) {
                //没有case,默认like搜索。
                switch ($k) {
                    case 'Name':
                        $seach[] = [$k, 'like', "%{$v}%"];
                        break;
                    default:
                }
            }
        }
        $page = $this->setPage($request);

        if(isset($seach)){
            $data = hlyun_oa_process_category::where($seach)->orderBy('ID','DESC')->skip($page['skip'])->limit($page['pageSize'])->get()->toarray();
            $count = hlyun_oa_process_category::where($seach)->count();
        }else{
            $data = hlyun_oa_process_category::orderBy('ID','DESC')->skip($page['skip'])->limit($page['pageSize'])->get()->toarray();
            $count = hlyun_oa_process_category::count();
        }
        $pageCount = ceil($count / $page['pageSize']);
        if ($count == 0) {
            $startCount = $page['skip'];
        } else {
            $startCount = $page['skip'] + 1;
        }
        $endCount = $page['skip'] + count($data);
        return ['code' => 0, 'data' => $data, 'pageCount' => $pageCount, 'count' => $count, 'startCount' => $startCount, 'endCount' => $endCount];
    }
    public function addCategorySub(){
        $request=\Request::all();
        $validator = Validator::make($request, [
            'Name' => 'required',
        ], [
            'Name.required' => '操作名称不能为空',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors()->all()[0];
            return response()->json(['code'=>600,'data'=>$errors]);
        }
        $res=hlyun_oa_process_category::create(['Name'=>$request['Name'],'Status'=>1]);
        if($res){
            return ['code'=>0,'data'=>'增加成功'];
        }else{
            return ['code'=>600,'data'=>'增加失败'];
        }
    }
    public function editCategoryOpen(Request $request){
        $request=\Request::all();
        $res=hlyun_oa_process_category::where('ID',$request['id'])->first();
        return ['code'=>0,'data'=>$res];
    }
    public function editCategorySub(){
        $request=\Request::all();
        $res=hlyun_oa_process_category::where('ID',$request['id'])->Update(['Name'=>$request['Name']]);
        if($res){
            return ['code'=>0,'data'=>'修改成功'];
        }else{
            return ['code'=>600,'data'=>'修改失败'];
        }
    }

    public function delCategorySub(){
        $request=\Request::all();
        $res=hlyun_oa_process_category::where('ID',$request['id'])->delete();
        if($res){
            return ['code'=>0,'data'=>'删除成功'];
        }else{
            return ['code'=>600,'data'=>'删除失败'];
        }
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