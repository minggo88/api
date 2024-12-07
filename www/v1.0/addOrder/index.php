<?php
include dirname(__file__) . "/../../lib/TradeApi.php";
// if($_SERVER['REMOTE_ADDR']!='61.74.240.65') {$exchangeapi->error('001','시스템 정검중입니다.');}
$exchangeapi->token = session_create_id();
session_start();
session_regenerate_id(); // 로그인할때마다 token 값을 바꿉니다.

// -------------------------------------------------------------------- //

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('slave');
$text = "";
// 이제 PHP에서 $dataArray를 원하는 방식으로 처리할 수 있습니다.
if (isset($_POST['dataArray'])) {
	$dataArray = $_POST['dataArray'];
// 예를 들어, 각 데이터 항목에 접근하여 처리하는 방법:
	foreach ($dataArray as $data) {
		$send_name: $data['send_name'];
		$send_call: $data['send_name'];
		$send_address: $data['send_name'];
		$payment_type: $data['send_name'];
		$payment: $data['send_name'];
		$payment_name: $data['send_name'];
		$item : $data['send_name'];
		$item_cnt : $data['send_name'];
		$receive_name : $data['send_name'];
		$receive_call : $data['send_name'];
		$receive_address_num : $data['send_name'];
		$receive_address : $data['send_name'];

		$insert_sql = " INSERT INTO kkikda.js_test_order 
			(payment_type, payment, payment_name, item_cnt, order_item, send_name, send_call, send_address, receive_address, receive_name, receive_call, receive_address_num, send_date, box_cnt, receive_code, send_type, move, send_message) 
			VALUES('$payment_type', '$payment', '$payment_name', $item_cnt, '$item', '$send_name', '$send_call', '$send_call', '$receive_address', '$receive_name', '$receive_call', '$receive_address_num', 'N', 0, 'N', 'N', 'N', 'N');";

		$tradeapi->query_one($insert_sql);
		$r['msg'] = 'check : '.$text;
	}
} else {
    $tradeapi->error('000', __('데이터형 오류'));
}

$tradeapi->success($r);

// --------------------------------------------------------------------------- //
