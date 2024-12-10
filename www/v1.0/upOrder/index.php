<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
//$tradeapi->checkLogin();
//$userno = $tradeapi->get_login_userno();
$dataArray = setDefault(loadParam('dataArray'), '');
$item = $dataArray[0]['order_item'];

$search_sql = 
    "SELECT item_index from js_test_item WHERE i_value LIKE '%$item%';";
$search_data = $tradeapi->query_list_object($search_sql);


$send_date= $dataArray[0]['send_date'];
$send_name= $dataArray[0]['send_name'];
$send_call= $dataArray[0]['send_call'];
$send_address= $dataArray[0]['send_address'];
$payment_type= $dataArray[0]['payment_type'];
$payment= $dataArray[0]['payment'];
$payment_name= $dataArray[0]['payment_name'];
$item_cnt = $dataArray[0]['item_cnt'];
$box_count = $dataArray[0]['box_count'];
$receive_name = $dataArray[0]['receive_name'];
$receive_call = $dataArray[0]['receive_call'];
$receive_address_num = $dataArray[0]['receive_address_num'];
$receive_address = $dataArray[0]['receive_address'];
$receive_code = $dataArray[0]['receive_code'];
$move = $dataArray[0]['move'];
$send_message = $dataArray[0]['send_message'];
$order_index = $dataArray[0]['order_index'];


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
			item_cnt='$item_cnt', send_name='$send_name', send_call='$send_call', send_address='$send_address', receive_address='$receive_address', 
			receive_name='$receive_name', receive_call='$receive_call', receive_address_num='$receive_address_num', send_date='$send_date', 
			box_cnt=$box_count, receive_code='$receive_code', move='$move', send_message='$send_message'
		WHERE order_index=$order_index;";
$t_data = $tradeapi->query_list_object($up_sql);

$tradeapi->success($up_sql);
