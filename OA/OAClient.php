<?php

namespace App\OA;

class OAClient
{
    /**
     * 返回节点流向信息
     */
   public function apply($processId='',$data=''){
        
        $params=[
            'processId' => $processId,
            'data' => $data,
            'sso_token'=>request()->all()['sso_token']
        ];
        // dd(config('oa.OAAPIURL'));
        $ch = curl_init();
        curl_setopt_array($ch,[
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER=>1, 
            // CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=UTF-8'],
            CURLOPT_TIMEOUT=>config('oa.OAAPITIMEOUT'),
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_URL => config('oa.OAAPIURL').config('oa.OAAPIROUTE'),

        ]);
       
        $result   = curl_exec($ch);
        return json_decode($result,true)?:['code'=>600,'data'=>'oa接口错误!'];
   }


   public function oalink($index=''){        
        $params=[
            'index' => $index,
            'url'=>config('oa.OAAPIURL'),
            'processId' => "test",
            'data' => "test",
            'sso_token'=>request()->all()['sso_token']
        ];
        // dd(config('oa.OAAPIURL'));
        $ch = curl_init();
        curl_setopt_array($ch,[
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER=>1, 
            // CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=UTF-8'],
            CURLOPT_TIMEOUT=>config('oa.OAAPITIMEOUT'),
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_URL => config('oa.OAAPIURL').config('oa.OAAPIROUTE'),

        ]);
        $result   = curl_exec($ch);
        $data=json_decode($result,true)?:"OA未检索到请求链接";
        if($data)$data=$data['data']??"OA未检索到请求链接";
        return $data;

    }

    /**
     * @Descripttion: 追加参数专用
     * @param parame 为参数数组
     * @Author: czm
     * @return: 
     * @Date: 2019-10-10 17:38:41
     */
    public function appendParam($url='',$params=[]){
        $url=parse_url($url);
        if(!isset($url['query']))return $url;
        parse_str($url['query'],$query_data);
        $query_data['type']=base64_encode(json_encode(['ID'=>$query_data['type']]+$params));
        $url['query']=$query_data;
        $t=function($url_arr){
            $new_url = $url_arr['scheme'] . "://".$url_arr['host'];
            if(!empty($url_arr['port']))
                $new_url = $new_url.":".$url_arr['port'];
            $new_url = $new_url . $url_arr['path'];
            if(!empty($url_arr['query']))
                // $query='';
                // foreach($url_arr['query'] as $k=>$v){
                //     $query.=$k.'='.$v.'&';
                // }
                // $new_url = $new_url . "?" . rtrim($query,'&');
                $new_url = $new_url . "?" . http_build_query($url_arr['query']);
            if(!empty($url_arr['fragment']))
                $new_url = $new_url . "#" . $url_arr['fragment'];
            return $new_url;
        };
        return $t($url);
    }

   public function auditing($userId='',$userName='',$organizationId='',$organizationName='',$data='',$processId=''){
        $params=[
            'processId' => $processId,
            'data' => $data,
            'organizationId'=> $organizationId,
            'organizationName' => $organizationName,
            'userId'=> $userId,
            'userName' => $userName,
            'sso_token'=>\Request::cookie('sso_token')
        ];
        $ch = curl_init();
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=UTF-8'],
            CURLOPT_TIMEOUT=>config('oa.OAAPITIMEOUT'),
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_URL => config('oa.OAAPIURL'),
        ]);
        $result   = curl_exec($ch);
        return json_decode($result,true)?:['code'=>600,'data'=>'oa接口错误!'];
   }
}