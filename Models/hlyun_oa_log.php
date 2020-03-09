<?php

namespace App\Models;

use App\Events\oaLogEvent;
use App\Models\Better\Model;
use Illuminate\Support\Facades\Schema;
// use Illuminate\Database\Eloquent\SoftDeletes;

class hlyun_oa_log extends Model
{
    protected $table='hlyun_oa_log';
    protected $guarded = ['ID'];
    public $timestamps=true;
    
    protected $dispatchesEvents = [
        // 'saving' => oaLogEvent::class,
    ];

    protected $blacklist=[
        'ID','FlowId','Content','CreatedAt','UpdatedAt'
    ];

    public static function log($request,$content,$flowId='',$apiId='')
    {
       
        $model=static::where('FlowId',$flowId)->first();
       
        if(empty($model)){
            $model = new static;
            $model->FlowId=$flowId;
            $content=date("Y-m-d H:i:s").": ".$content;
            
        }else{
            $content=$model->Content.PHP_EOL.date("Y-m-d H:i:s").": ".$content;
        }
       
        $columns = Schema::getColumnListing($model->table);
        $indexField=array_diff($columns,$model->blacklist);
        //以后有关联主键 直接添加即可
        foreach($indexField as $val){
            $request[$val]=$request[$val]??'';
            if(!empty($request[$val])){
                (is_array($request[$val])&&$model->$val=implode(',',$request[$val]))||$model->$val=$request[$val];
            }
        }
        
        $model->Content=$content;
        $model->save();

        if($apiId){
            hlyun_oa_index_log_api::firstOrCreate(['LogId'=>$model->ID,'ApiId'=>$apiId]);
        }
    }
}
