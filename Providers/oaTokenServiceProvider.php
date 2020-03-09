<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider as Provider;
use App\oaToken;

// class oaTokenServiceProvider extends ServiceProvider
class oaTokenServiceProvider implements Provider
{
    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveById($identifier){
        return $this->checkAvailableUser($identifier);
    }
    
    private function checkAuailableUser($token){

        $info=[];
        $lock=false;
        foreach($token as $k=>$v){
            $info[$k]=$v;
            if(!$lock){
                $lock=$v?true:false;
            }
        }
        if(!$lock){
            return null;
        }

        return new oaToken(['token'=>$token]+$info);
     
    }

    
    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array  $credentials
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials){
        if(empty($credentials)){
            return null;
        }
        $credentials=array_filter(array_only($credentials,['sso_token','center_token']));
        return $this->checkAuailableUser($credentials);
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  array  $credentials
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials){
        
    }
    public function retrieveByToken($identifier, $token)
    {
    }

    public function updateRememberToken(Authenticatable $user, $token)
    {
    }
}
