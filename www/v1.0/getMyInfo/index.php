<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
// $tradeapi->checkLogin();
$userno = $tradeapi->get_login_userno();

// validate parameters

// 슬레이브 디비 사용하도록 설정.
$tradeapi->set_db_link('slave');

// get my member information
$r = $userno ? $tradeapi->get_member_info($userno) : (object) array();
if(isset($r->pin)) {unset($r->pin);}
if(isset($r->userpw)) {unset($r->userpw);}

// get permission code
$r->permission = $tradeapi->get_permission_code($r->bool_confirm_mobile, $r->bool_confirm_idimage, $r->bool_confirm_bank ? true : false);

// set country infor
if($r->mobile_country_code) {
    $r->country = $tradeapi->db_get_row("js_country", array('code'=>$r->mobile_country_code));
    $r->country->name = __($r->country->name) ;
}

// response
$tradeapi->success($r);
