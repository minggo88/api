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

// 예를 들어, 각 데이터 항목에 접근하여 처리하는 방법:
foreach ($dataArray as $data) {
	$send_name: $data['send_name'];
	$send_call: $data['send_call'];
	$send_address: $data['send_address'];
	$payment_type: $data['payment_type'];
	$payment: $data['payment'];
	$payment_name: $data['payment_name'];
	$item : $data['item'];
	$item_cnt : $data['item_cnt'];
	$receive_name : $data['receive_name'];
	$receive_call : $data['receive_call'];
	$receive_address_num : $data['receive_address_num'];
	$receive_address : $data['receive_address'];

	$insert_sql = " INSERT INTO kkikda.js_test_order 
		(payment_type, payment, payment_name, item_cnt, order_item, send_name, send_call, send_address, receive_address, receive_name, receive_call, receive_address_num) 
		VALUES('$payment_type', '$payment', '$payment_name', $item_cnt, '$item', '$send_name', '$send_call', '$send_address', '$receive_address', '$receive_name', '$receive_call', '$receive_address_num');";
	
	$exchangeapi->query($insert_sql);
	$r['msg'] = 'check : '.$text;
};

$exchangeapi->transaction_end('commit');// DB 트랜젝션 끝


// response
$exchangeapi->success(array('token'=>"success",'my_wallet_no'=>"1111",'userno'=>"2222"));

// --------------------------------------------------------------------------- //
