<?php

namespace App\Models;

use App\Models\Better\Model;

class hlyun_oa_form extends Model
{
    protected $table='hlyun_oa_form';
    protected $guarded = ['ID'];
    protected $casts=[
        'Field'=>'array',
        'ObjId'=>'array',
    ];
    public static function form($request)
    { 
        if(isset($request['id'])){
            $model = static::find($request['id']);
        }else{        
            $model = new static;

        }
        $model->Name=$request['Name'];
        $model->Creater=$request['Creater'];
        $model->Comments=$request['Comments'];
        $model->Status=$request['Status'];
        $model->save();
        //删除关联
        return true;
    }
}
