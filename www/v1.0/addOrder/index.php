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

$dataArray = setDefault(loadParam('dataArray'), '');
// 이제 PHP에서 $dataArray를 원하는 방식으로 처리할 수 있습니다.

$exchangeapi->set_db_link('master');

$exchangeapi->transaction_start();// DB 트랜젝션 시작

/*
$send_name: $dataArray['send_name'];
$send_call: $dataArray['send_call'];
$send_address: $dataArray['send_address'];
$payment_type: $dataArray['payment_type'];
$payment: $dataArray['payment'];
$payment_name: $dataArray['payment_name'];
$item : $dataArray['item'];
$item_cnt : $dataArray['item_cnt'];
$receive_name : $dataArray['receive_name'];
$receive_call : $dataArray['receive_call'];
$receive_address_num : $dataArray['receive_address_num'];
$receive_address : $dataArray['receive_address'];

$insert_sql = " INSERT INTO kkikda.js_test_order (payment_type, payment, payment_name, item_cnt, order_item, send_name, send_call, send_address, receive_address, receive_name, receive_call, receive_address_num) 
	VALUES('$payment_type', '$payment', '$payment_name', '$item_cnt', '$item', '$send_name', '$send_call', '$send_address', '$receive_address', '$receive_name', '$receive_call', '$receive_address_num');";
*/
//$exchangeapi->query($insert_sql);

$exchangeapi->transaction_end('commit');// DB 트랜젝션 끝


// response
//$exchangeapi->success(array('token'=>"success",'my_wallet_no'=>"1111",'userno'=>"2222"));
$exchangeapi->success($dataArray[0]);

// --------------------------------------------------------------------------- //
