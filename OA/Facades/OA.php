<?php

namespace App\OA\Facades;

use Illuminate\Support\Facades\Facade;

class OA extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'OA';
    }
}