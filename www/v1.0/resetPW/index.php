<?php
include dirname(__file__) . "/../../lib/TradeApi.php";
$tradeapi->set_logging(true);
$tradeapi->set_log_dir($tradeapi->log_dir.'/'.basename(__dir__).'/');
$tradeapi->set_log_name('');
$tradeapi->write_log("REQUEST: " . json_encode($_REQUEST));

$t 	= checkEmpty($_REQUEST['t'], 't');
//$pin 		= checkEmpty($_REQUEST['pin'], 'pin');  // $_POST로 확인하면 request/index.php 에서 애러납니다.
$password 		= checkEmpty($_REQUEST['password'], 'password');  // $_POST로 확인하면 request/index.php 에서 애러납니다.

// memebr meta 정보 확인
$meta_info = $tradeapi->query_fetch_object("SELECT userno, `value` FROM js_member_meta WHERE `value`='{$tradeapi->escape($t)}' and `name`='tmp_pw'");
if(!$meta_info) {
	$tradeapi->error('200', __('요청 정보를 찾지 못했습니다.'));
}
$tmp_pw_time = $tradeapi->query_one("SELECT `value` FROM js_member_meta WHERE `userno`='{$tradeapi->escape($meta_info->userno)}' and `name`='tmp_pw_time'");
if($tmp_pw_time < time()-(60*30)) {
	$tradeapi->error('210', __('기간이 만료되었습니다.'));
}

$userpw_db = md5($password);
//$pin_db = md5($pin);

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('master');

// 비번변경
//$tradeapi->query("UPDATE js_member SET pin='{$tradeapi->escape($pin_db)}', userpw='{$tradeapi->escape($userpw_db)}' WHERE userno='{$tradeapi->escape($meta_info->userno)}' ");
$tradeapi->query("UPDATE js_member SET userpw='{$tradeapi->escape($userpw_db)}' WHERE userno='{$tradeapi->escape($meta_info->userno)}' ");

// meta 삭제
$tradeapi->query("DELETE FROM js_member_meta WHERE userno='{$tradeapi->escape($meta_info->userno)}' AND (`name`='tmp_pw_time' OR `name`='tmp_pw') ");

// response
$tradeapi->success();
