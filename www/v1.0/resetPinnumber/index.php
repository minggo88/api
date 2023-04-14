<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
$tradeapi->checkLogin();

$t 	= checkEmpty($_REQUEST['t'], 't');
//$pin 		= checkEmpty($_REQUEST['pin'], 'pin');  // $_POST로 확인하면 request/index.php 에서 애러납니다.
$pinnumber 		= checkEmpty($_REQUEST['pinnumber'], 'pinnumber');  // $_POST로 확인하면 request/index.php 에서 애러납니다.

// memebr meta 정보 확인
$meta_info = $tradeapi->query_fetch_object("SELECT userno, `value` FROM js_member_meta WHERE `value`='{$tradeapi->escape($t)}' and `name`='tmp_pinnumber'");
if(!$meta_info) {
    $tradeapi->error('200', __('요청 정보를 찾지 못했습니다.'));
}
$tmp_pw_time = $tradeapi->query_one("SELECT `value` FROM js_member_meta WHERE `userno`='{$tradeapi->escape($meta_info->userno)}' and `name`='tmp_pinnumber_time'");
if($tmp_pw_time < time()-(60*30)) {
    $tradeapi->error('210', __('기간이 만료되었습니다.'));
}

$userpin_db = md5($pinnumber);
//$pin_db = md5($pin);

// 마스터 디비 사용하도록 설정.기
$tradeapi->set_db_link('master');

// 비번변경
//$tradeapi->query("UPDATE js_member SET pin='{$tradeapi->escape($pin_db)}', userpw='{$tradeapi->escape($userpw_db)}' WHERE userno='{$tradeapi->escape($meta_info->userno)}' ");
$tradeapi->query("UPDATE js_member SET pin='{$tradeapi->escape($userpin_db)}' WHERE userno='{$tradeapi->escape($meta_info->userno)}' ");

// meta 삭제
$tradeapi->query("DELETE FROM js_member_meta WHERE userno='{$tradeapi->escape($meta_info->userno)}' AND (`name`='tmp_pinnumber_time' OR `name`='tmp_pinnumber') ");

// response
$tradeapi->success();
