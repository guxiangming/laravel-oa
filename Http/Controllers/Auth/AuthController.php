<?php

namespace App\Http\Controllers\Auth;

use App\Models\htms_center_log;
use App\Models\htms_center_users;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Validator;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use App\Models\htms_center_log as Log;

class AuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Registration & Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users, as well as the
    | authentication of existing users. By default, this controller uses
    | a simple trait to add these behaviors. Why don't you explore it?
    |
    */

    // use AuthenticatesAndRegistersUsers, ThrottlesLogins {
    //     AuthenticatesAndRegistersUsers::postLogin as laravelPostLogin;
    // }
    use AuthenticatesUsers;
    /**
     * Where to redirect users after login / registration.
     *
     * @var string
     */
    protected $redirectTo = '/MyHome/index';
    protected $redirectAfterLogout = '/';

    /**
     * Create a new authentication controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest', ['except' => 'logout']);
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'Cellphone' => 'required|regex:/^1[34578][0-9]{9}$/|unique:users',
            'password' => 'required|confirmed|min:6',
        ]);
    }

    /**
     *  用户登录提交
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function postLogin(Request $request)
    {
        $field = is_numeric($request->input('Cellphone')) ? 'Cellphone' : 'Account';
        $request->merge([$field => $request->input('Cellphone')]);
        $this->username = $field;
        return $this->login($request);
    }

    /**
     * 从 AuthenticatesUsers 派生的登录处理逻辑
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\Response|\Illuminate\View\View
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(Request $request)
    {
        $this->validateLogin($request);
        /*自定义登录验证    */
        $userData = htms_center_users::where($this->loginUsername(), $request->Cellphone)->get()->toArray();
        if (count($userData) == 1) {
            if ($userData[0]['Status'] == 2) {
                return view('login.login', ['disable' => '用户已被禁止登录']);
            }
        } else {
            return view('login.login', ['disable' => '用户名或密码错误']);
        }
        $throttles = $this->isUsingThrottlesLoginsTrait();

        if ($throttles && $lockedOut = $this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            $this->sendLockoutResponse($request);
        }

        $credentials = $this->getCredentials($request);#获取验证字段
        //这里 getGuard() 获取的使用 null 认证，即为config\auth.php中默认的 web
        if (Auth::guard($this->getGuard())->attempt($credentials, $request->has('remember'))) {
            //登记最后登录时间
            htms_center_users::where('ID', Auth::user()->ID)->update(['LastLoginTime' => date('Y-m-d H:i:s', time())]);

            /*//如果上次没有登出的，维护上一次的登出时间，在线时长
            $this->dealLastLog();*/
            //登陆成功
            $operator['type']    = 2;
            $operator['UserId']  = Auth::user()->ID;
            $operator['operate'] = 2;
            $operator['content'] = "登入系统";
            $thisLog             = Log::operatorLog($operator);
            htms_center_log::recordLogOnTime('in',$thisLog);//登入日志标识记录到session;以后用来记onTime

            return $this->handleUserWasAuthenticated($request, $throttles);
        }
        //记录以尝试次数，每次加一
        $this->incrementLoginAttempts($request);

        return $this->sendFailedLoginResponse($request);
    }

    /**
     * 处理当前用户的其他登陆日志
     */
    protected function dealLastLog()
    {
        $log = Log::where('UserId', Auth::user()->ID)->orderBy('ID', 'desc')->first();
        if (!is_null($log) && is_null($log->LogoutTime)) {
            $date       = date('Y-m-d H:i:s');
            $onlineTime = strtotime($date) - strtotime($log->AtTime);
            if ($onlineTime > 7200) {//超过两小时过期，
                $update = ['OnlineTime' => 120, 'LogoutTime' => date('Y-m-d H:i:s', strtotime($log->AtTime) + 7200)];
            } else {//在线中，换个浏览器登陆的。
                $update = ['OnlineTime' => $onlineTime / 60, 'LogoutTime' => $date];
            }
            Log::where('ID', $log['ID'])->update($update);
        }
    }

    /**
     * 派生的登出记录
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function logout()
    {
        htms_center_log::recordLogOnTime('out');//登出状态登记到日志
        Auth::guard($this->getGuard())->logout();
        return redirect(property_exists($this, 'redirectAfterLogout') ? $this->redirectAfterLogout : '/');
    }
}
