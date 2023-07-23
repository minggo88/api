<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
$tradeapi->checkLogin();
$userno = $tradeapi->get_login_userno();

//메인반출내용
$search_sql = 
    "SELECT takeout_item_name AS t_name,
            takeout_item_count AS t_cnt, 
            takeout_item_production_date AS t_pdate, 
            takeout_request_date AS t_rdate, 
            takeout_state AS t_state  
            FROM js_takeout_item 
            WHERE takeout_userno = '{$userno}' 
                AND takeout_state != 'I' 
                AND takeout_item_idx = 'main' 
            ORDER BY js_takeout_item.takeout_index DESC;";
$t_data = $tradeapi->query_list_object($search_sql);

$tradeapi->success($t_data);
