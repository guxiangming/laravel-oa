<?php

namespace App\Api\Controller;

use \Illuminate\Http\Request;
use App\Api\Exception\NotFoundApiException;
use App\Api\Exception\ForbiddenApiException;
use App\Api\Exception\ErrorException;
use Illuminate\Cache\RateLimiter;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Redis;
use PhpParser\Node\Stmt\TryCatch;
use App\Models\hlyun_oa_api;

class ApiController
{
    /**
     * default
     */
    protected $namespace_controller = 'App\Api\Controllers';

    public function index($request)
    {
        $pathInfo = $request->getPathInfo();
        $method = $request->getMethod();
        //match mysql or redis list
        $res = $this->findRoute($pathInfo, $method);
        if ($res) {
            //自定义组件
            //call_func_controller 
            try{
                
                $type=$this->getType($res['CategoryType']);
                $controller = $this->namespace_controller .'\\'.$type.'\\'.explode('@',$res['Controller'])[0];
                $action =explode('@',$res['Controller'])[1];
            }catch(\Throwable $t){
                throw new ErrorException('Controller设定错误请纠正');
            }
            // return gettype($request);
            //默认传入request 对象
         
            return call_user_func([new $controller, $action], $request);
            
        } else {
            return Response('API has no input system and cannot be requested', 404);
        }
    }

    public function findRoute($pathInfo, $method)
    {
        //mysql查找
        $info=hlyun_oa_api::where('RequestType',$method)->where('Route',$pathInfo)->first();
        $info=collect($info)->toArray();
        if ($info) {
            //mysql查找-解码
            if($info['Status']==0)throw new ForbiddenApiException;
            return $info;
        }
        throw new NotFoundApiException;
    }

    public function requestId($id){
        $info=hlyun_oa_api::find($id)->toArray();
        
        if (!$info) {
            throw new NotFoundApiException;
        }
        if($info['Status']==0)throw new ForbiddenApiException;
        try{
            $type=$this->getType($info['CategoryType']);
            $controller = $this->namespace_controller .'\\'.$type.'\\'.explode('@',$info['Controller'])[0];
            $action =explode('@',$info['Controller'])[1];
        }catch(\Throwable $t){
            // dd($info);
            throw new ErrorException("编号为：{$id};api-Controller设定错误请纠正");
        }
        $request=\Request::all();
        return call_user_func([new $controller, $action], $request);
        
    }

     /**
     * Get all of the parameter names for the route.
     *
     * @return array
     */
    public function parameterNames()
    {
        if (isset($this->parameterNames)) {
            return $this->parameterNames;
        }

        return $this->parameterNames = $this->compileParameterNames();
    }

    /**
     * Get the parameter names for the route.
     *
     * @return array
     */
    protected function compileParameterNames()
    {
        preg_match_all('/\{(.*?)\}/', $this->getDomain().$this->uri, $matches);

        return array_map(function ($m) {
            return trim($m, '?');
        }, $matches[1]);
    }
    public function getType($data){
        switch($data){
            case '1' :
            return 'form';
            break;
            case '2' :
            return 'get';
            break;
            case '3' :
            return 'syn';
            break;
        }
    }
}
