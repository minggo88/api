<?php
include dirname(__file__) . "/../../lib/TradeApi.php";


// -------------------------------------------------------------------- //


// 거래소 api는 토큰을 전달 받을때만 작동하도록 되어 있어서 로그인시 token을 생성해 줍니다.
$exchangeapi->token = session_create_id();
session_start();
session_regenerate_id(); // 로그인할때마다 token 값을 바꿉니다.

// 로그인 세션 확인.
// $exchangeapi->checkLogout();

$name = setDefault(loadParam('c_name'), '');
$call = setDefault(loadParam('c_call'), '');

// --------------------------------------------------------------------------- //

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('slave');

// 전체데이터 가져오기
$sql = "  SELECT test_customer_index AS c_index, 
		c_name, c_call, c_address1, c_address2 
	FROM js_test_customer 
	WHERE 1=1 
		AND c_name LIKE '%$name%'
		AND c_call LIKE '%$call%'
	ORDER BY c_name ASC; ";

$sms_data = $tradeapi->query_list_object($sql);

$tradeapi->success($sms_data);

