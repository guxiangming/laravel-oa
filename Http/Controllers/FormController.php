<?php

namespace App\Http\Controllers;

use App\Http\ModuleClass\HelperClass;
use function GuzzleHttp\json_encode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use QL\QueryList;
use Validator;
use App\Models\hlyun_oa_form;
use App\Rules\checkNameUnique;

class FormController extends Controller
{
    public function formIndex(){
        return view('form.formIndex');
    }

    public function formList(\Request $request){
        $request=$request::all();
        $seach=[];
        foreach ($request as $k => $v) {
            if ($v||$v==0) {
                //没有case,默认like搜索。
                switch ($k) {
                    case 'Name':
                        $seach[] = [$k, 'like', "%{$v}%"];
                        break;
                    default:
                       break; 
                }
            }
        }
        $page = $this->setPage($request);
        $sql=hlyun_oa_form::when(!empty($seach),function($query) use($seach){
            $query->where($seach);
        });
        $data =$sql->skip($page['skip'])->limit($page['pageSize'])->orderBy('UpdatedAt', 'desc')->get()->toarray();
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
    
    public function defineForm(Request $request){
        $request=$request->input();
        $validator = Validator::make($request, [
            'id' => 'required',
        ], [
            'id' => '参数丢失',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors()->all()[0];
            return response()->json(['code' => 600, 'data' => $errors]);
        }
        $res = hlyun_oa_form::where('ID',$request['id'])->first();
        $type = isset($request['type']) ? $request['type'] : '';
        return view('form.defineForm', ['id' => $res['ID'], 'formName' => $res['Name'], 'type' => $type]);
    }
    //提交表单
    public function addFormSub(Request $request){
        $request=$request->all();
        $validator = Validator::make($request, [
            'Name' => ['required',new checkNameUnique('hlyun_oa_form')],
            'Status'=>'required',
            // 'Comments'=>'required',
            'Creater'=>'required',
        ], [
            'Name.required' => '表单名称不能为空',
            'Comments.required' => '说明不能为空',
            'Creater.required' => '创始人不能为空',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors()->all()[0];
            return response()->json(['code' => 600, 'data' => $errors]);
        }
        return hlyun_oa_form::form($request) === true ?
        ['code' => 0, 'data' => '成功！'] : ['code' => 600, 'data' => '失败!存在异常'];
    }

    public function editFormOpen(Request $request){
        $request=$request->all();
        $data=hlyun_oa_form::where('ID',$request['id'])->first();
        return ['code' => 0, 'data' =>$data] ;
    }
    //表单基础信息修改
    public function editFormSub(Request $request){
       
        return $this->addFormSub($request);
    }

    /**
     * TODO: 转发表单操作
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @author czm
     * 2018/11/22 10:27
     */
    public function form(Request $request){
        $type = $request->input('op');
        if(!$type){
            return response()->json(['code' => 600, 'data' => '未定义op动作']);
        }
        $data = $request->except('_url', '_token', 'actionType');
        switch ($type) {
            default:
                return $this->$type($data);
                break;
        }
    }
 
    /**
     * TODO: 初始化表单
     * @param $request
     * @return string
     * @author czm
     * 2018/11/22 10:05
     */
    public function enterDefineForm($request){
        $form = hlyun_oa_form::find($request['id']);
        $init = [
            'hasSignature' => [
                'hasHtmlSignature' => true,
                'hasPhoneSignature' => true,
            ],

            'getForm' => [
                'FIELD_COUNTER' => '1',
                'PRINT_MODEL' =>$form->Content,
            ],
            'Field'=>$form->Field

        ];
        return response()->json($init);
    }

    //字段控制设置
    public function editFieldOpen(Request $request){
        return ['code'=>0,'data'=>hlyun_oa_form::find($request->id)->Field];
    }

    public function editFieldSub(Request $request){
        $request=$request->all();
        $field=array('name','title');
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
       
        hlyun_oa_form::find($request['id'])->update(['Field'=>$result]);    
        return ['code'=>0,'data'=>'修改成功'];
    }
    //删除表单
    public function delFormSub(Request $request)
    {
        $request=$request->all();
        hlyun_oa_form::find($request['id'])->delete();    
        return ['code'=>0,'data'=>'删除成功'];
    }

    //表单保存
    public function saveForm(){
        $request=\Request::all();
        $form = hlyun_oa_form::find($request['formID']);
        $form->Content = $request['formContent'];
        $ids=QueryList::html($request['formContent'])->find('*')->attrs('id')->all();
        $form->ObjId=array_unique(array_filter($ids));
        $form->save();
        return ['code'=>0,'data'=>'保存 成功'];
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
