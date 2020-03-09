<?php
namespace App;

use Illuminate\Http\Request;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Support\Arrayable;
use App\Http\ModuleClass\HelperClass;
use App\Models\Sso\hlyun_sso_users;
use App\Models\Sso\hlyun_sso_organizations;
use App\Models\Sso\hlyun_sso_position;
use App\Models\Sso\hlyun_sso_roles;
use App\Exceptions\OAException;


class oaToken extends GenericUser implements \ArrayAccess, Arrayable
{
    /**
     * All of the user's attributes.
     *
     * @var array
     */
    protected $attributes;

    /**
     * Create a new generic User object.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes)
    {
        parent::__construct($attributes);
        $this->attributes=$this->ssoInfo()+$attributes;
    
    }


    public function centerPermissions()
    {
    
        if(!isset($this->attributes['center_token'])){
            return [];
        }

        if(!\Session::get('centerPermissions')&&!\is_array(\Session::get('centerPermissions'))){
            $permissions=json_decode(HelperClass::curlCenter(['method'=>'POST','params'=>['permissions'=>''],'route'=>'/api/user/permissions']),true);
            $permissions=$permissions['data']??[];
            \Session::put('centerPermissions',$permissions);
            \Session::save();
            return  $permissions;
        }else{
            return \Session::get('centerPermissions');
        }
    }

    public function ssoPermissions()
    {
        if(!isset($this->attributes['sso_token'])){
            return [];
        }
        
    }

    public function ssoInfo($indexKey=null)
    {
        if(!isset($this->attributes['sso_token'])){
            return [];
        }
        $prefix=$this->attributes['sso_token'];
        if(!\Session::get($prefix.'ssoInfo')&&!\is_array(\Session::get($prefix.'ssoInfo'))){

            // //获取当前账号的角色
            // $select[]='hlyun_sso_users.ID';
            // $select[]='hlyun_sso_users.Cellphone';
            // $select[]='hlyun_sso_users.Name';
            // $select[]='hlyun_sso_users.Email';
            // $select[]='hlyun_sso_users.Sex';
            // $select[]='hlyun_sso_users.Image';
            // $select[]='hlyun_sso_users.WeChat';
            // $select[]='hlyun_sso_users.Openid';
            // $select[]='hlyun_sso_index_organization_user.Pid';
            // $select[]='hlyun_sso_organizations.Name as Company';
            // $select[]='hlyun_sso_organizations.AccreditId';
            // $select[]='hlyun_sso_organizations.Pid as CompanyPid';
            // $select[]='hlyun_sso_organizations.OrganizationType';
            // $select[]='hlyun_sso_organizations.ID as CompanyId';
        
            // $user=hlyun_sso_users::leftJoin('hlyun_sso_index_organization_user','hlyun_sso_index_organization_user.UserId','=','hlyun_sso_users.ID')->leftJoin('hlyun_sso_organizations','hlyun_sso_organizations.ID','=','hlyun_sso_index_organization_user.OrganizationId')->
            // where('hlyun_sso_organizations.AccreditId',$this->attributes['sso_token']['AccreditId'])->where('hlyun_sso_users.Cellphone',$this->attributes['sso_token']['Cellphone'])->first($select);
            // $user=$user->toArray();     
            // //区分企业版本还是集团版本
            // if (!empty($user['CompanyPid'])) {
            //     $Groupcompany=hlyun_sso_organizations::find($user['CompanyPid']);
            //     $user['GroupAccreditId'] =$Groupcompany['AccreditId'];
            //     $user['IsGroupVersion']=1;
            //     //集团全部企业码---用于运力管理数据读取
            //     $user['GroupAccreditIds'] =\DB::connection('hlyun_sso')->table('hlyun_sso_organizations')->where('Pid',$user['CompanyPid'])->where('Pid','!=',0)->whereIn('OrganizationType',[1,2])->union(\DB::table('hlyun_sso_organizations')->where('ID',$user['CompanyPid'])->whereIn('OrganizationType',[1,2])->select('AccreditId'))->get(['AccreditId'])->pluck('AccreditId')->toArray();
            //     $user['GroupCompany']=$Groupcompany['Name'];
            // }else{
            //     //集团全部企业码---当前企业和下级
            //     $user['GroupAccreditIds'] =\DB::connection('hlyun_sso')->table('hlyun_sso_organizations')->where('Pid',$user['CompanyId'])->union(\DB::table('hlyun_sso_organizations')->where('ID',$user['CompanyId'])->select('AccreditId'))->get(['AccreditId'])->pluck('AccreditId')->toArray();
            //     $user['GroupAccreditId'] =$user['AccreditId'];
            //     $user['GroupCompany']= $user['Company'];
            //     $user['IsGroupVersion']=$user['OrganizationType']==1?1:0;

            // }
            // //获取当前用户职务
            // $user['Task'] = hlyun_sso_position::leftJoin('hlyun_sso_index_position_user','hlyun_sso_index_position_user.PositionId','hlyun_sso_position.ID')->where('UserId',$user['ID'])->selectRaw('group_concat(distinct(Position) separator "+")as Task')->pluck('Task')->toArray()[0];
            // //获取当前账号的角色
            // $sql='hlyun_sso_roles.AccreditId='.$user['AccreditId'].' or hlyun_sso_index_roles_distribution.DistributionAccreditId='.$user['AccreditId'].' or (hlyun_sso_roles.Public=1 and hlyun_sso_roles.AccreditId in ('.implode(',', $user['GroupAccreditIds']).'))';
            // $RoleIds=hlyun_sso_roles::leftJoin('hlyun_sso_organizations','hlyun_sso_organizations.AccreditId','=','hlyun_sso_roles.AccreditId')
            //     ->leftJoin('hlyun_sso_index_roles_distribution','hlyun_sso_index_roles_distribution.RoleId','=','hlyun_sso_roles.ID')
            //     ->whereRaw($sql)->pluck('hlyun_sso_roles.ID')->toArray();
            // $roles=hlyun_sso_roles::leftJoin('hlyun_sso_index_roles_users','hlyun_sso_index_roles_users.RoleId','=','hlyun_sso_roles.ID')->where('UserId',$user['ID'])->whereIn('RoleId',$RoleIds)->pluck('Name')->toArray();
            // $user['RoleIds']=array_unique($RoleIds);
            // $user['Roles']=implode(',', array_unique($roles));
            // //获取当前人部门
            // $departments=\DB::connection('hlyun_sso')->select("select o.Name,o.ID from hlyun_sso_index_organization_user as ou left join hlyun_sso_organizations as o on ou.OrganizationId=o.ID  where ou.UserId=".$user['ID'].' and o.AccreditId='.$user['AccreditId'] .' and o.OrganizationType=3');
            // $department_str=[];
            // foreach ($departments as $key => $value) {
            //     $department_str[]=$value->Name;
            // }
            // $department_str=implode(',', array_unique($department_str));
            // $user['Departments']=json_decode(json_encode($departments),true);
            // $user['Department_str']=$department_str;
            // //根据权限表获取所有Moduleid ,根据hlyun_sso_index_permission_role_accredit获取跨企业数据权限，没有授权的模块默认为当前企业权限
            // $ModuleIds=\DB::connection('hlyun_sso')->table('hlyun_sso_permissions')->where('Label','like','%accredit_data_manage')->pluck('ModuleId');
            // $role_accredit=\DB::connection('hlyun_sso')->table('hlyun_sso_index_permission_role_accredit')
            //     ->leftJoin('hlyun_sso_index_roles_users','hlyun_sso_index_roles_users.RoleId','=','hlyun_sso_index_permission_role_accredit.RoleId')
            //     ->leftJoin('hlyun_sso_roles','hlyun_sso_roles.ID','hlyun_sso_index_permission_role_accredit.RoleId')
            //     ->where('hlyun_sso_index_roles_users.UserId',$user['ID'])
            //     ->whereIn('hlyun_sso_roles.AccreditId',[$user['AccreditId'],$user['GroupAccreditId']])
            //     ->get(['hlyun_sso_index_permission_role_accredit.AccreditId','hlyun_sso_index_permission_role_accredit.ModuleId']);
            // $role_accredit =json_decode(json_encode($role_accredit),true);
            // $AccreditIds=[];
            // foreach ($ModuleIds as  $ModuleId) {
            //     $AccreditIds[$ModuleId]=[];
            //     foreach ($role_accredit as $key => $value) {
            //         if ($value['ModuleId']==$ModuleId) {
            //             $AccreditIds[$ModuleId]=json_decode($value['AccreditId']);
            //         }
            //     }
            //     if (!in_array($user['AccreditId'], $AccreditIds[$ModuleId])) {
            //         $AccreditIds[$ModuleId][]=$user['AccreditId'];
            //     }
            // }
            // $user['AccreditIds']=$AccreditIds;

            $params['sso_token']=$this->attributes['sso_token'];
            $params['RequestData']=['UserInfo'=>1];
            $curlRequest=['method'=>'GET','params'=>$params,'route'=>'/api/organization'];
            $result=HelperClass::curl($curlRequest);
            // var_dump($result);exit;
            // echo "<pre>";
            $res=json_decode($result,true);
            if (isset($res['code'])&&$res['code']==0) {
                $user=$res['data']['UserInfo'];
            }else{
                throw new OAException('oaToken获取用户信息错误！');
            }

            \Session::put($prefix.'ssoInfo',$user);
            $ssoInfo=$user;
        }else{
            $ssoInfo=\Session::get($prefix.'ssoInfo');
        }
        //手动保存session
        \Session::save();
        if(!empty($indexKey)){
            
            return $ssoInfo[$indexKey];
        }else{  
            return is_array($ssoInfo)?$ssoInfo:[];
        }
    }


    /**
     * Get the name of the unique identifier for the user.
     *
     * @return string
     */
    public function getAuthIdentifierName()
    {

        return 'token';
        // return 'sso_token';
    }

    /**
     * @param $key
     * @return mixed|null
     */
    public function getAttribute($key)
    {
        if (!$key) {
            $key = '';
        }

        // If the attribute exists in the attribute array or has a "get" mutator we will
        // get the attribute's value. Otherwise, we will proceed as if the developers
        // are asking for a relationship's value. This covers both types of values.
        if (array_key_exists($key, $this->attributes)) {
            return $this->getAttributeValue($key);
        }
        return null;
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * @param $key
     * @return mixed
     */
    public function getAttributeValue($key)
    {
        $value = $this->getAttributeFromArray($key);

        return $value;
    }

    /**
     * @param $key
     * @return mixed
     */
    protected function getAttributeFromArray($key)
    {
        if (isset($this->attributes[$key])) {
            return $this->attributes[$key];
        }
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return !is_null($this->getAttribute($offset));
    }

    /**
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->getAttribute($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->setAttribute($offset, $value);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->attributes[$offset], $this->relations[$offset]);
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    /**
     * 用于访问可用属性组成的数组
     * @see Arrayable
     * @return array
     */
    public function toArray()
    {
        return $this->attributes;
    }

}