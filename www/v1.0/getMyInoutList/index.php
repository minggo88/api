<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
$tradeapi->checkLogin();
$userno = $tradeapi->get_login_userno();

// validate parameters
$symbol = checkSymbol(strtoupper(checkEmpty($_REQUEST['symbol'], 'symbol')));//0: 전체 , 1: 입금, 2: 출금

// 슬레이브 디비 사용하도록 설정.
$tradeapi->set_db_link('slave');


$txns = $tradeapi->get_inout_list($userno,  $symbol);


// response
$tradeapi->success($txns);
