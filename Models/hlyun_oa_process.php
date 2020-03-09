<?php

namespace App\Models;

use App\Exceptions\OAException;
use App\Http\ModuleClass\HelperClass;
use App\Models\Better\Model;
use Illuminate\Support\Facades\DB;

class hlyun_oa_process extends Model
{

    protected $table='hlyun_oa_process';
    protected $guarded = ['ID'];
    protected $casts=[
       
    ];
    
    public static function process($request)
    { 
        if(isset($request['id'])){
            $model = static::find($request['id']);
        }else{        
            $model = new static;

        }
        $model->Name=$request['Name'];
        $model->Rank=$request['Rank'];
        $model->Comments=$request['Comments'];
        $model->Status=$request['Status'];
        $model->OrganizationStatus=$request['OrganizationStatus'];
        $model->save();
        //删除关联
        DB::table('hlyun_oa_index_process_form')->where('ProcessId',$model->ID)->delete();
        DB::table('hlyun_oa_index_process_category')->where('ProcessId',$model->ID)->delete();
        DB::table('hlyun_oa_index_process_category')->insert([
            'ProcessId'=>$model->ID,
            'CategoryId'=>$request['CategoryId']
        ]);
        DB::table('hlyun_oa_index_process_form')->insert([
            'ProcessId'=>$model->ID,
            'FormId'=>$request['FormId']
        ]);
        return true;
    }
  
   
    //关联查询

    public function hasManyCate(){
        return $this->hasManyThrough(hlyun_oa_process_category::class, hlyun_oa_index_process_category::class, 'ProcessId', 'ID','ID','CategoryId');

    }

    public function hasManyForm(){
        return $this->hasManyThrough(hlyun_oa_form::class, hlyun_oa_index_process_form::class, 'ProcessId', 'ID','ID','FormId');
    }

    public function hasManyStep(){
        return $this->hasMany(hlyun_oa_process_step::class, 'ProcessId', 'ID');
    }

    //查询规则
    public function scopeStatus($query,$value){
        return $query->where('Status',$value);
    }


    public function scopeName($query,$value){
        return $query->where('Name','like',"%$value%");
    }

    public function scopeCategoryId($query,$value){
        $ProcessId=hlyun_oa_index_process_category::where('CategoryId',$value)->pluck('ProcessId')->toArray();
        return $query->whereIn('ID',$ProcessId);
    }

    public function scopeFormId($query,$value){
        $ProcessId=hlyun_oa_index_process_form::where('FormId',$value)->pluck('ProcessId')->toArray();
        return $query->whereIn('ID',$ProcessId);
    }
    //排除子流程
    public function scopeNochlid($query){
        return $query->where('Rank',1);
    }

    //查询子流程
    public function scopeChlid($query){
        return $query->where('Rank',2);
    }


    public function scopeRole($query){
        
        $curlRequest = ['method'=>'GET', 'params'=>['CompanyID'=>oaUser()->ssoInfo('CompanyId'),'Type'=>1,'ProductId'=>''], 'route'=>'/api/companyProductDefault'];
        $result = HelperClass::curl($curlRequest);

        $res = json_decode($result, true);
        if(isset($res['code'])&&$res['code']==0){
            $ProcessIds=hlyun_oa_index_process_menu::whereIn('MenuId',$res['menusId'])->pluck('ProcessId')->toArray();
            return $query->whereIn('ID',$ProcessIds);
        }else{
            // HelperClass::errorlog('获取系统失败',$result);
           throw new OAException('获取系统与菜单应用失败,请联系管理员！');
        }
        
    }
    //验证中调用自定义验证规则
   
    
}
