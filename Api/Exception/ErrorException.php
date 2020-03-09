<?php

namespace App\Api\Exception;

use Exception;

class ErrorException extends Exception
{

    public function __construct($message)
    {
        parent::__construct();
        $this->message=$message;
    }
    public function render()
    {

        return response(json_encode(['code'=>400,'data'=>$this->message],JSON_UNESCAPED_UNICODE));
    }
}
