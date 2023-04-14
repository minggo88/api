<?php
include dirname(__file__) . "/../../lib/TradeApi.php";
$tradeapi->checkLogin();
$userno = $tradeapi->get_login_userno();

$tradeapi->set_logging(true);
$tradeapi->set_log_dir($tradeapi->log_dir.'/'.basename(__dir__).'/');
$tradeapi->set_log_name('');
$tradeapi->write_log("REQUEST: " . json_encode($_REQUEST));


$password = checkEmpty($_REQUEST['password'], 'password');
$new_password = checkEmpty($_REQUEST['new_password'], 'new_password');

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('master');

$userpw_db = md5($password);
$newpa_db = md5($new_password);

// 계정 정보 확인.
$member = $tradeapi->query_fetch_object("SELECT `userpw` FROM js_member WHERE userno = '".$tradeapi->escape($userno)."'");

if(!$member) {
    $tradeapi->error('041', __('The information does not match. Please check your ID!'));
}

// 비밀번호 확인.
if($userpw_db != $member->userpw) {
    $tradeapi->error('031', __('The information does not match. Please check your Password!'));
}

// 비번변경
$tradeapi->query("UPDATE js_member SET userpw='{$tradeapi->escape($newpa_db)}' WHERE userno='{$tradeapi->escape($userno)}' ");

// response
$tradeapi->success();
