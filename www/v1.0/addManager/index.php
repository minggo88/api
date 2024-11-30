<?php
include dirname(__file__) . "/../../lib/ExchangeApi.php";
// if($_SERVER['REMOTE_ADDR']!='61.74.240.65') {$exchangeapi->error('001','시스템 정검중입니다.');}
$exchangeapi->set_logging(true);
// $exchangeapi->set_log_dir(__dir__.'/../../log/'.basename(__dir__).'/');
// if(__API_RUNMODE__=='live'||__API_RUNMODE__=='loc') {
	$exchangeapi->set_log_dir($exchangeapi->log_dir.'/'.basename(__dir__).'/');
// } else {
	// $exchangeapi->set_log_dir(__dir__.'/');
// }

// 로그인 세션 확인.
// $exchangeapi->checkLogout();

$m_id = setDefault(loadParam('add_id'), '');
$m_password = setDefault(loadParam('add_pw'), '');
$m_name = setDefault(loadParam('add_name'), '');
$m_call = setDefault(loadParam('add_call'), '');
$m_use = setDefault(loadParam('add_use'), '');


// --------------------------------------------------------------------------- //

$exchangeapi->token = session_create_id();
session_start();
session_regenerate_id(); // 로그인할때마다 token 값을 바꿉니다.

// 로그인 세션 확인.
// $exchangeapi->checkLogout();

// --------------------------------------------------------------------------- //

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('slave');

// 가입

$sql = " INSERT INTO `kkikda`.`js_test_manager` (`m_id`, `m_password`, `m_name`, `m_call`, `m_use`) 
		VALUES ('$m_id', '$m_password', '$m_name', '$m_call', '$m_use');";

$sms_data = $tradeapi->query_list_object($sql);

$tradeapi->success($sms_data);
