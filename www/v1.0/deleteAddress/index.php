<?php
include dirname(__file__)."/../../lib/ExchangeApi.php";
$exchangeapi->set_logging(true);
// if(__API_RUNMODE__=='live'||__API_RUNMODE__=='loc') {
	$exchangeapi->set_log_dir($exchangeapi->log_dir.'/'.basename(__dir__).'/');
// } else {
// 	$exchangeapi->set_log_dir(__dir__.'/');
// }
$exchangeapi->set_log_name('');
$exchangeapi->write_log("REQUEST: " . json_encode($_REQUEST));

// 로그인 세션 확인.
$exchangeapi->checkLogin();
$userno = $exchangeapi->get_login_userno(); 

// validate parameters
$symbol = checkSymbol( checkEmpty(loadParam('symbol'), 'symbol') );

// --------------------------------------------------------------------------- //

// 마스터 디비 사용하도록 설정.
$exchangeapi->set_db_link('master');

// check previos address
$r = $exchangeapi->delete_wallet($userno, $symbol);

// response
$exchangeapi->success($r);
