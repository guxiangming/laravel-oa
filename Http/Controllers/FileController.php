<?php

namespace App\Http\Controllers;

use App\Http\ModuleClass\HelperClass;
use App\Http\ModuleClass\OcrClass;

use function GuzzleHttp\json_encode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use QL\QueryList;
use Validator;
use App\Rules\checkNameUnique;
use App\Models\hlyun_oa_process_flow_auditing_files;
use App\Models\hlyun_oa_process_flow;


/**
 * @Description: oa附件管理函数
 * @Param: 
 * @Author: czm
 * @return: 
 * @Date: 2019-07-17 18:05:07
 */
class FileController extends Controller                                                                                                                                            
{   
    //文件路径

    private $path;

    public function __construct()
    {
        $this->path=storage_path();
    }

    public function uploadFiles(Request $request)
    {
        $request=$request->all();
        $validator = \Validator::make($request, [
            'file' => 'required',
            'AuditingId'=>'required',
        ], [
            'file.required'    => '文件不能为空',
            'auditingId.required'    => '审核ID不能为空',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors()->all()[0];
            return response()->json(['code'=>600,'data'=>$errors]);
        }
        $auditingId=$request['AuditingId'];
        $files=hlyun_oa_process_flow::uploadFiles([$request['file']]);

        $files=collect($files)->map(function($item,$k) use($auditingId){
            //记录用户信息
            $item['UserId']=oaUser()->ssoInfo('ID');
            $item['UserName']=oaUser()->ssoInfo('Name');
            $item['AuditingId']=$auditingId;
            $item['CreatedAt']=date("Y-m-d H:i:s");
            $item['UpdatedAt']=date("Y-m-d H:i:s");
            return $item;
        })->toArray();
        // dd($files);
        $res=hlyun_oa_process_flow_auditing_files::create($files[0]);
        // dd($res);
        if ($res){
            $data=hlyun_oa_process_flow_auditing_files::select('ID','FileName as name')->where('ID',$res->ID)->get()->toArray();
            if(isset($request['type'])&&$request['type']=='licences'){
                $license = OcrClass::getBusinessLicense(storage_path($files[0]['FilePath']))['data'];
                return response()->json(['code'=>0,'data'=>$data,
                'LicencesName'=>$license['Name'],
                'JurisdicalPerson'=>$license['LegalRepresentative'],
                'UniformCreditCode'=>$license['UniformCreditCode'],
                'LicencesType'=>$license['Type'],
                'LicencesAddress'=>$license['Address'],
                'RegisteredCapital'=>$license['RegisteredCapital'],
                'EstablishmentDate'=>$license['EstablishmentDate'],
                'BusinessDateStartAt'=>$license['EstablishmentDate'],
                'BusinessDateEndAt'=>$license['BusinessDateEndAt'],
                'ScopeOfBusiness'=>''
                ]);
            }else{
                //记录数据库
                return response()->json(['code'=>0,'data'=>$data]);
            }
           
            
        
        }else{
            return response()->json(['code'=>600,'data'=>'失败联系管理员']);
        }
    }

    public function downFiles()
    {
        $request=  \Request::all();
        $validator = \Validator::make($request, [
            'ID' => 'required',
        ], [
            'ID.required'    => '文件不能为空',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors()->all()[0];
            return view('error.error',['msg'=>$errors]);
        }
        $file=hlyun_oa_process_flow_auditing_files::find($request['ID']);
        $pathToFile = storage_path().$file->FilePath;
        // dd($pathToFile);
        if (is_file($pathToFile)) {
            return response()->download($pathToFile,$file->FileName); 
        }else{
            return ['code'=>600,'data'=>'读取错误联系管理员'];
        }
    }

    public function delFiles()
    {
        $request=  \Request::all();
        $validator = \Validator::make($request, [
              'ID' => 'required',
          ], [
              'ID.required'    => '文件不能为空',
          ]);
          if ($validator->fails()) {
              $errors = $validator->errors()->all()[0];
              return response()->json(['code'=>600,'data'=>$errors]);
          }
          $file=hlyun_oa_process_flow_auditing_files::find($request['ID']);
          $filePath=storage_path($file->FilePath);
          $file->delete();
          if (is_file($filePath)&&$filePath!=storage_path()){
             unlink($filePath);
             
             return response()->json(['code'=>0,'msg'=>'成功']);
          }
          return response()->json(['code'=>600,'msg'=>'不存在文件,记录已清除']);
    }
}
