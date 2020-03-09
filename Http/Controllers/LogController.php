<?php

namespace App\Http\Controllers;

use App\Http\ModuleClass\HelperClass;


use function GuzzleHttp\json_encode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use QL\QueryList;
use Validator;
use App\Models\hlyun_oa_log;


class LogController extends Controller                                                                                                                                            
{   
    public function logIndex(Request $request)
    {
        
        return view('log.logIndex');
    }

    public function logList(Request $request)
    {
        $sql=hlyun_oa_log::leftJoin('hlyun_oa_process_flow','hlyun_oa_log.FlowId','=','hlyun_oa_process_flow.ID');
        if($request['Api']){
            $sql=$sql->leftJoin('hlyun_oa_index_log_api','hlyun_oa_index_log_api.LogId','=','hlyun_oa_log.ID')
                ->leftJoin('hlyun_oa_index_log_api','hlyun_oa_index_log_api.ApiId','=','hlyun_oa_api.ID')
                ->where('hlyun_oa_api.Name','like',"%{$request['Api']}%");
        }

        if($request['WorkNumber']){
            $sql=$sql->where('hlyun_oa_log.WorkNumber',$request['WorkNumber']);
        }

        if($request['FlowNumber']){
            $sql=$sql->where('hlyun_oa_process_flow.FlowNumber',$request['FlowNumber']);
        }

        if($request['UserName']){
            $sql=$sql->where('hlyun_oa_process_flow.UserName',$request['UserName']);
        }

        if($request['Content']){
            $sql=$sql->where('hlyun_oa_log.Content','like',"%{$request['Content']}%");
        }

        if($request['CreadetAt']){
            switch($request['CreadetAt']){
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
            $sql=$sql->where('hlyun_oa_process_flow.CreatedAt','>',$date);
        }
        $page = $this->setPage($request);
        $data=$sql->skip($page['skip'])->limit($page['pageSize'])->orderBy('hlyun_oa_log.UpdatedAt','desc')->get(['hlyun_oa_log.*','hlyun_oa_process_flow.*','hlyun_oa_log.ID as ID'])->toArray();
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

    public function logDetailOpen()
    {
        $data=hlyun_oa_log::where('ID',request()->all('id'))->value('Content');
        // dd($data->Content);
        return ['code'=>0,'data'=> $data];
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
