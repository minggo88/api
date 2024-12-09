<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
//$tradeapi->checkLogin();
//$userno = $tradeapi->get_login_userno();

//메인반출내용
$search_sql = 
    "SELECT send_date, payment_type, payment, send_call, payment_name, receive_name, it_name.i_value AS order_item, item_cnt,
 			order_item2, item_cnt2, order_item3, item_cnt3, order_item4, item_cnt4, order_item5, item_cnt5,
 			receive_call, box_cnt, receive_address_num, receive_address, receive_code, move, move_cnt, send_message,
 			order_index ,stime
		 FROM js_test_order
	    LEFT JOIN js_test_item AS it_name ON order_item = item_index

            ORDER BY send_date DESC;";
$t_data = $tradeapi->query_list_object($search_sql);

$tradeapi->success($t_data);
