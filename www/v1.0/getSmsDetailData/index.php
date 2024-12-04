<?php
include dirname(__file__) . "/../../lib/TradeApi.php";


// -------------------------------------------------------------------- //


// 거래소 api는 토큰을 전달 받을때만 작동하도록 되어 있어서 로그인시 token을 생성해 줍니다.
$exchangeapi->token = session_create_id();
session_start();
session_regenerate_id(); // 로그인할때마다 token 값을 바꿉니다.

// 로그인 세션 확인.
// $exchangeapi->checkLogout();

$s_index = setDefault(loadParam('s_index'), '');

// --------------------------------------------------------------------------- //

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('slave');

// 전체데이터 가져오기
$sql = " SELECT 
			sms_index, 
			CASE 
				WHEN cu.c_name IS NULL THEN sms.call 
				ELSE CONCAT(cu.c_name, '(', sms.call, ')') 
			END AS name, 
			tvalue, 
			stime, 
			complete, 
			complete_manager
		FROM 
			js_test_sms AS sms
		LEFT JOIN 
			js_test_customer AS cu 
		ON 
			sms.call = cu.c_call
		WHERE 
			complete <> 'D'
			AND sms.CALL = (SELECT ys_sms.call FROM js_test_sms AS ys_sms WHERE sms_index = '$s_index')
		ORDER BY  
			complete = 'N' DESC, 
			sms_index DESC";

$sms_data = $tradeapi->query_list_object($sql);

$tradeapi->success($sms_data);

