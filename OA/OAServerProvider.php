<?php
/**
 * Created by PhpStorm.
 * User: czm
 * Date: 2019/9/6
 * Time: 17:24
 */

namespace App\OA;
use Illuminate\Support\ServiceProvider;


class OAServerProvider  extends ServiceProvider
{
    public function register()
    {
       
        $this->app->singleton('OA',function(){
            //初始化所有配置
            $this->mergeConfig();
            $client = new OAClient();   
            return $client;    
        });
    }

    /**
     * Merge configurations.
     *
     * @return void
     */
    protected function mergeConfig()
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/oa.php', 'oa');
    }
}