<?php

namespace App\Models;

use App\Models\Better\Model;
use Illuminate\Support\Facades\DB;

class hlyun_oa_index_process_form extends Model
{

    protected $table='hlyun_oa_index_process_form';
    
    
}

// @if (isset($permission['WorkflowManage']))
//                         <li>
//                             <div class="second">
//                                     <a  data-href="/product/business" data-title="流程服务中心"><span class="font-speak">流程服务中心</span><i class="fa fa-angle-down"></i></a>
//                                 </div>
//                                 <ul class="thress_menu" style="display: none;">
//                                     <li class="br_btm"><em class="em_th none"></em></li>
//                                     <li>
//                                         <div class="seconds">
//                                             <a data-href="{{ $AppAddress['OA'].'/process/categoryManage?center_token='.session('center_token')}}" data-title="配置管理"><span class="font-speak">配置管理</span></a>
//                                         </div>
//                                     </li>
//                                     @if (isset($permission['DefineCategory']))
//                                     <li>
//                                         <div class="seconds">
//                                             <a data-href="{{ $AppAddress['OA'].'/process/categoryManage?center_token='.session('center_token')}}" data-title="定义分类"><span class="font-speak">定义分类</span></a>
//                                         </div>
//                                     </li>
//                                     @endif
//                                     @if (isset($permission['DefineWorkflow']))
//                                     <li>
//                                         <div class="seconds">
//                                             <a data-href="{{ $AppAddress['OA'].'/process/workflowIndex?center_token='.session('center_token')}}" data-title="定义流程"><span class="font-speak">定义流程</span></a>
//                                         </div>
//                                     </li>
//                                     @endif
//                                     @if (isset($permission['DefineForm']))
//                                     <li>
//                                         <div class="seconds">
//                                             <a data-href="{{ $AppAddress['OA'].'/process/createForm?center_token='.session('center_token')}}" data-title="定义表单"><span class="font-speak">定义表单</span></a>
//                                         </div>
//                                     </li>
//                                     @endif
//                                 </ul>
//                         </li>
//                         @endif