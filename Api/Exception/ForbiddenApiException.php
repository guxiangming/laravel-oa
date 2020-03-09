<?php

namespace App\Api\Exception;

use Exception;

class ForbiddenApiException extends Exception
{

    public function __construct()
    {
        parent::__construct();
    }
    public function render()
    {
        return response(json_encode(['code'=>403,'data'=>'路由已被关闭'],JSON_UNESCAPED_UNICODE));
    }
}
