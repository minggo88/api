<?php
include dirname(__file__) . "/../../lib/TradeApi.php";
$tradeapi->set_logging(true);
// if(__API_RUNMODE__=='live'||__API_RUNMODE__=='loc') {
	$tradeapi->set_log_dir($tradeapi->log_dir.'/'.basename(__dir__).'/');
// } else {
// 	$tradeapi->set_log_dir(__dir__.'/');
// }
$tradeapi->set_log_name('');
$tradeapi->write_log("REQUEST: " . json_encode($_REQUEST));

/**
 * 가입 확인
 */

// 로그인 세션 확인.
$tradeapi->checkLogin();
$userno = $tradeapi->get_login_userno();
$userid = $tradeapi->get_login_userid();
$dbpin = $tradeapi->query_one("SELECT pin FROM js_member WHERE userid='{$tradeapi->escape($userid)}'");

// validate parameters
$pin = checkEmpty(loadParam('pin')); // 계좌 송금 비번.

$tradeapi->write_log("pw: " . $dbpin);

// pin 번호 확인.
if($dbpin != md5($pin)) {
    $tradeapi->error('025',__('Please enter the correct PIN number.'));
}

// response
$tradeapi->success(true);
