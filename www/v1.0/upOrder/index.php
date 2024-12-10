<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
//$tradeapi->checkLogin();
//$userno = $tradeapi->get_login_userno();
$dataArray = setDefault(loadParam('dataArray'), '');
$item = $dataArray['order_item'];

$search_sql = 
    "SELECT item_index from js_test_item WHERE i_value LIKE '%$item%';";
$search_data = $tradeapi->query_list_object($search_sql);
//$order_item = $search_data[0];


$send_date= $dataArray['send_date'];
$send_call= $dataArray['send_call'];
$payment_type= $dataArray['payment_type'];
$payment= $dataArray['payment'];
$payment_name= $dataArray['payment_name'];
$item_cnt = $dataArray['item_cnt'];
$box_count = $dataArray['box_cnt'];
$receive_name = $dataArray['receive_name'];
$receive_call = $dataArray['receive_call'];
$receive_address_num = $dataArray['receive_address_num'];
$receive_address = $dataArray['receive_address'];
$receive_code = $dataArray['receive_code'];
$move = $dataArray['move'];
$send_message = $dataArray['send_message'];
$order_index = $dataArray['order_index'];

/*$item2 = $dataArray[0]['item2'];
$item_cnt2 = $dataArray[0]['item_cnt2'];
$item3 = $dataArray[0]['item3'];
$item_cnt3 = $dataArray[0]['item_cnt3'];
$item4 = $dataArray[0]['item4'];
$item_cnt4 = $dataArray[0]['item_cnt4'];
$item5 = $dataArray[0]['item5'];
$item_cnt5 = $dataArray[0]['item_cnt5'];*/

//메인반출내용
$up_sql = 
    "UPDATE kkikda.js_test_order
		SET payment_type='$payment_type', payment='$payment', payment_name='$payment_name', order_item='$search_data',
			item_cnt='$item_cnt', send_call='$send_call', receive_address='$receive_address', 
			receive_name='$receive_name', receive_call='$receive_call', receive_address_num='$receive_address_num', send_date='$send_date', 
			box_cnt='$box_count', receive_code='$receive_code', move='$move', send_message='$send_message' 
		WHERE order_index='$order_index';";
//$t_data = $tradeapi->query_list_object($up_sql);

$tradeapi->success($search_data);
