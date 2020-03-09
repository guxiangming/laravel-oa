<?php

namespace App\Providers;

use Illuminate\Auth\RequestGuard;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Http\ModuleClass\HelperClass;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
        //认证驱动的服务提供者
        \Auth::provider('oaToken',function($app){
            return new oaTokenServiceProvider();
        });
        //自定义授权服务
        \Auth::extend('oaToken',function($app,$name,$config){
            //注册开放获取
            $guard = new RequestGuard(
                function ($request,oaTokenServiceProvider $provider) {
                    //设置参数优先cookie
                    return $provider->retrieveByCredentials($request->input() + $request->cookie());
                }, $app['request'],\Auth::createUserProvider($config['provider'])
            );
            $app->refresh('request', $guard, 'setRequest');
            return $guard;
        });
        //用户权限查询 缓存permissions 后台管理功能
         view()->composer('*',function($view){
            $view->with([
                'centerPermissions' => oaUser()->centerPermissions(),
            ]);
        });
       

    }
}
