<?php

namespace App\Api;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;

class ApiServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    protected $namespace = 'App\Http\Controllers';

    public function __construct()
    {

    }

    public function boot()
    {
        //

    }
    // 初始化路由
    public function initRoute(){
     
        $this->mapApiRoutes();
        $this->mapWebRoutes();
        Route::group([],function(){
            Route::any('{path}', function (\Illuminate\Http\Request $request) {         
                // return $request->getPathInfo();
                    $controller = "App\\Api\\Controller\\".ucfirst('Api').'Controller';
                    $action='index';
                    return \App::make($controller)->$action($request);
                })->where('path', '.*');
        });
    }   

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::middleware('web')
             ->namespace($this->namespace)
             ->group(base_path('routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::prefix('api')
             ->middleware('api')
             ->namespace($this->namespace)
             ->group(base_path('routes/api.php'));
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
   
        $this->initRoute();
    }
    
}
