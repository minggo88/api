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
$exchangeapi->token = session_create_id();
session_start();
session_regenerate_id(); // 로그인할때마다 token 값을 바꿉니다.

// 로그인 세션 확인.
// $exchangeapi->checkLogout();

// --------------------------------------------------------------------------- //

// 마스터 디비 사용하도록 설정.
$exchangeapi->set_db_link('master');

$exchangeapi->transaction_start();// DB 트랜젝션 시작

// 전체데이터 가져오기

$sql = " SELECT 
			sms_index, 
			CASE 
				WHEN cu.c_name IS NULL THEN sms.call 
				ELSE CONCAT(cu.c_name, '(', sms.call, ')') 
			END AS name, 
			tvalue, 
			stime, 
			complete, 
			complete_manager
		FROM 
			js_test_sms AS sms
		LEFT JOIN 
			js_test_customer AS cu 
		ON 
			sms.call = cu.c_call
		WHERE 
			complete <> 'D'
		ORDER BY  
			complete = 'N' DESC, 
			sms_index DESC";

$sms_data = $tradeapi->query_list_object($sql);

$tradeapi->success($sms_data);

