<?php

namespace App\Models;

use App\Models\Better\Model;
use Illuminate\Support\Facades\DB;
use phpDocumentor\Reflection\Types\Boolean;

class hlyun_oa_process_step extends Model
{

    protected $table='hlyun_oa_process_step';
    protected $guarded = ['ID'];
    protected $casts=[
        'Style'=>'array',
        'Field'=>'array',
        'OutCondition'=>'array',
        'BackCondition'=>'array',
        'OrganizationId'=>'array',
        // 'OrganizationName'=>'array',
        'RoleId'=>'array',
        // 'RoleName'=>'array',
        'TaskId'=>'array',
        'UserId'=>'array',
        // 'UserName'=>'array',
        'Transactors'=>'array',
        'Setting'=>'array',  
    ];
    //获取类型1
    public function scopeStepFirst($query,$value)
    {
        //Type 步骤类型 是否第一步2 其他1
        return $query->where('Type',2)->where('ProcessId',$value);
    }

    //查询非第一步剩余的步骤
    public function scopeFlowStep($query,$value)
    {
        return $query->where('Type','!=',2)->where('ProcessId',$value);
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

    public function hasManyForm(){
        return $this->hasManyThrough(hlyun_oa_form::class, hlyun_oa_index_process_form::class, 'ProcessId', 'ID','ProcessId','FormId');
    }

    public function hasOneAuditing(){
        return $this->hasOne(hlyun_oa_process_flow_auditing::class,'StepId','ID');
    }
    //推算可倒退的节点信息
    public static function getFlowBeforeStep($processId,$stepId){
        $model=new static;
        $data=$model->where('ProcessId',$processId)->get()->toArray();
        $link=$model->getFlowStep($data);
        // dd($link);
        $result=[];
        foreach($link as $k=>$v){
            $index=array_search($stepId,$v);
            // dd( $v,$index,$stepId);
            if($index!==false&&$index!=0){
                $result=array_merge($result,array_filter(array_slice($v,0,$index)));
            }
        }
        // dd($result);
        return array_unique($result);
    }
    //根据流程ID 与步骤ID推算出下一次节点
    public static function getFlowNextStep($processId,$stepId){
       
        $model=new static;
        $data=$model->where('ProcessId',$processId)->get()->toArray();
        $link=$model->getFlowStep($data);
        
        $result=[];
        foreach($link as $k=>$v){
            // dd($stepId);
            $index=array_search($stepId,$v);
            // dd($index);
            // dd( $v,$index,$stepId);
            if($index!==false&&isset($v[$index+1])){
                array_push($result,$v[$index+1]);
            }
        }
       
        return array_unique($result);
    }

    //渲染流程步骤
    public function getFlowStep($data){
        try{   
        // return true;
        $data=collect($data)->groupBy('Type')->toArray();
        //取出第一步骤
        $first=$data[2][0];
        // dd($first);
        //取出剩余步骤,并排序
        $other=collect($data[1])->keyBy('ID')->toArray();
        //转换为单向多分支链表
        $to=$first['To'];
        $result=[$first['ID']];
        hlyun_oa_process_step::$all=[];
        hlyun_oa_process_step::$link=false;
        $result=$this->getLink($other,$to,$result);
        // dd(hlyun_oa_process_step::$link);
        if(!hlyun_oa_process_step::$link){
            // dd(array_filter($result));
            return [array_filter($result)];
        }else{
            return hlyun_oa_process_step::$all;
        }
        }catch(\Throwable $t){
            // dd($t);
            throw new \App\Exceptions\OAException('流程路线读取错误请检查流程设定!第一步为必设定项');
        }
        // $first=collect()
    }
    static $all=[];
    static $link=false;
    public function getLink($other,$to,$result){
        $tos=explode(',',$to);    
        if($tos[0]==current($result)){
            return $result;
        } 
        if(count($tos)==1&&!in_array($tos[0],$result)){
            //分裂
            if(array_key_exists($tos[0],$other)){
                //求当前ID
                $tos[0]=(int)$tos[0];
                array_push($result,$tos[0]);
                $to=$other[$tos[0]];
                $result=$this->getLink($other,$to['To'],$result);
                
                return $result;
            }else{  
                array_push($result,$tos[0]);
                return $result;
            }
        }
        else{
            if(!hlyun_oa_process_step::$link){
                hlyun_oa_process_step::$link=true;
            }
            foreach($tos as $k=>$v){
                $bresult=$result;
                if(array_key_exists($v,$other)){
                    $v=(int)$v;
                    array_push($bresult,$v);
                    $v=$other[$v];
                    $bresult=$this->getLink($other,$v['To'],$bresult);
                    if($bresult!=null){
                        array_push(hlyun_oa_process_step::$all,$bresult);
                    }
                    // return $bresult;
                }
               
            }
        }
        
    }

    //判断数据是否被上锁
    protected static function IsDataLock($id){
        return static::find($id)->DataLock==2&&static::find($id)->Type==1?false:true;
    }
}
