<?php

namespace App\Models;

use App\Models\Better\Model;

class hlyun_oa_api extends Model
{
    protected $table='hlyun_oa_api';
    protected $guarded = ['ID'];
    public $timestamps=true;
    protected $casts=[
        'Request'=>'array',
        'Response'=>'array',
        'StatusCode'=>'array',
    ];
    public function scopeType($query,$value)
    {
        switch($value){
            case 'form':
                $value=1;
            break;
            case 'get':
                $value=2;
            break;
            case 'syn':
                $value=3;
            break;
        }
        return $query->where('CategoryType',$value);
    }

    public function scopeRoute($query,$value)
    {
        return $query->where('Route',$value);
    }

    public function scopeController($query,$value)
    {
        return $query->where('Controller',"like","%$value%");
    }

    public function scopeName($query,$value)
    {
        return $query->where('Name','like',"%$value%");
    }

    public function scopeAuthor($query,$value)
    {
        return $query->where('Author','like',"%$value%");
    }

    
    public static function api($request)
    {
       
        try{
            \DB::beginTransaction();
            if(isset($request['id'])){
                $model = static::find($request['id']);
            }else{           
                $model = new static;
                
            }

            // 格式化数据  
            $field=array('Request','Response');
            foreach($field as $value){
                if(!empty($request['param'][$value])){
                    $$value=$model->fieldParamSory($request['param'][$value],'field');
                
                }else{
                    $$value=array(0=>array('field'=>'','fieldType'=>'1','must'=>'1','des'=>''));
                }
    
            }
            
            if(!empty($request['StatusCode'])){
                $StatusCode=$model->fieldParamSory($request['StatusCode'],'code');
            }else{
                $StatusCode = array ( 0 => array ( 'code' => '200', 'des' => '成功'));
            }

            $model->Name=$request['Name'];
            $model->Route=$request['Route'];
            $model->CategoryType=$request['CategoryType']; 
            $model->Controller=$request['Controller']; 
            $model->Status=$request['Status'];        
            $model->Version=$request['Version'];
            $model->Description=$request['Description'];
            $model->Author=$request['Author'];
            $model->RequestType=$request['RequestType'];
            $model->ResponseType=$request['ResponseType'];
            //写入请求参数
            $model->Request=$Request;
            $model->Response=$Response;
            $model->StatusCode=$StatusCode;

            $model->save();
            // $info=$model->find($model->ID)->toArray();
            // if(extension_loaded('redis')){
            //     $rkey=$info['Route'].'@'.$info['RequestType'];
            //     $rval=json_encode($info,JSON_UNESCAPED_UNICODE);
            //     Redis::hset('api-route',$rkey,$rval);
            // }    
            \DB::commit();
            return true;
        }catch(\Throwable $t){
            \DB::rollback();

            return $t;
        }
       
      
    }
   
    /** 
     * 请求响应字段排序
     * @param $data 待排序数据
     * @param $fieldname 确定个数字段
     */
    public function fieldParamSory($data,$fieldname){
        $inkey=array_keys($data[$fieldname]);
        $outkey=array_keys($data);
        $result=array();
        foreach($inkey as $val){
            foreach($outkey as $value){
                $result[$val][$value]=$data[$value][$val];
            }
        }
        return $result;
    }
}
