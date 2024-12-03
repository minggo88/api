<?php
include dirname(__file__) . "/../../lib/TradeApi.php";


// -------------------------------------------------------------------- //


// 거래소 api는 토큰을 전달 받을때만 작동하도록 되어 있어서 로그인시 token을 생성해 줍니다.
$exchangeapi->token = session_create_id();
session_start();
session_regenerate_id(); // 로그인할때마다 token 값을 바꿉니다.

// 로그인 세션 확인.
// $exchangeapi->checkLogout();

$type_num = setDefault(loadParam('type_num'), '');
// --------------------------------------------------------------------------- //

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('slave');

// 전체데이터 가져오기
$sql = " SELECT endt_index, endt_text 
	FROM js_test_end_text 
	WHERE 1=1 ";
//$sql .= " AND i_type = '$type_num' ";
$sql .= " ORDER BY itype_name ASC;";
		
	
$text_data = $tradeapi->query_list_object($sql);

$tradeapi->success($text_data);

