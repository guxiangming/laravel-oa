<?php

namespace App\Api\Exception;

use Exception;

class NotFoundApiException extends Exception
{

    public function __construct()
    {
        parent::__construct();
    }
    public function render()
    {
        return response(json_encode(['code'=>404,'data'=>'路由未发现!请联系管理员'],JSON_UNESCAPED_UNICODE));
    }
}
