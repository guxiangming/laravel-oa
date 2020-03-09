<?php

namespace App\Models\Better;

use Illuminate\Database\Eloquent\Model as FrameModel;

class Model extends FrameModel
{
    /**
     * 修改模型主键
     *
     * @var string
     */
    protected $primaryKey = 'ID';

    /**
     * 修改创建时间、更新时间和软删除时间字段名称
     */
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';
    const DELETED_AT  = 'DeletedAt';

    /**
     * 配置访问器命名与当前模型的
     *
     * @see \Illuminate\Database\Eloquent\Concerns\HasAttributes::$attributes
     * 数组对应的 key 数组
     * @param  string $class
     * @return void
     */
    public static function cacheMutatedAttributes($class)
    {
        static::$mutatorCache[$class] = collect(static::getMutatorMethods($class))->all();
    }
}