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
$exchangeapi->set_log_name('');
$exchangeapi->write_log("REQUEST: " . json_encode($_REQUEST));

// -------------------------------------------------------------------- //


// 거래소 api는 토큰을 전달 받을때만 작동하도록 되어 있어서 로그인시 token을 생성해 줍니다.
// $exchangeapi->token = session_create_id();
session_start();
session_regenerate_id(); // 로그인할때마다 token 값을 바꿉니다.

// 로그인 세션 확인.
// $exchangeapi->checkLogout();

$mobile = setDefault(loadParam('call'), '');
$text = setDefault(loadParam('text'), '');



// --------------------------------------------------------------------------- //

// 마스터 디비 사용하도록 설정.
$exchangeapi->set_db_link('master');

$exchangeapi->transaction_start();// DB 트랜젝션 시작

// 가입

$sql = " INSERT INTO `kkikda`.`js_test_sms` (`call`, `tvalue`) VALUES ('$mobile', '$text') ";
$exchangeapi->query($sql);
$member = $exchangeapi->get_member_info_by_userid($userid);


$exchangeapi->transaction_end('commit');// DB 트랜젝션 끝


// response
$exchangeapi->success(array('token'=>"success",'my_wallet_no'=>"1111",'userno'=>"2222"));
