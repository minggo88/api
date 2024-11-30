<?php
include dirname(__file__) . "/../../lib/TradeApi.php";


// -------------------------------------------------------------------- //


// 거래소 api는 토큰을 전달 받을때만 작동하도록 되어 있어서 로그인시 token을 생성해 줍니다.
$exchangeapi->token = session_create_id();
session_start();
session_regenerate_id(); // 로그인할때마다 token 값을 바꿉니다.

// 로그인 세션 확인.
// $exchangeapi->checkLogout();

// --------------------------------------------------------------------------- //

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('slave');

$c_index = setDefault(loadParam('up_index'), '');
$c_name = setDefault(loadParam('up_name'), '');
$c_call = setDefault(loadParam('up_call'), '');
$c_address1 = setDefault(loadParam('up_address1'), '');
$c_address2 = setDefault(loadParam('up_address2'), '');


// 가입

$sql = " UPDATE `kkikda`.`js_test_customer` 
			SET `c_name`='$c_name', `c_call`='$c_call', `c_address1`='$c_address1', `c_address2`='$c_address2' 
			WHERE  `test_customer_index`='$c_index';";

$up_data = $tradeapi->query_list_object($sql);

$tradeapi->success($up_data);
