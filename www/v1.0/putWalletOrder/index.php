<?php
include dirname(__file__) . "/../../lib/ExchangeApi.php";

// 로그인 세션 확인.
$exchangeapi->checkLogin();
$userno = $exchangeapi->get_login_userno();

// validate parameters
$symbols = checkSymbol( checkEmpty(loadParam('symbols')) ); // BTC,ETH,LTC,..
$orders = checkEmpty(loadParam('orders')); // 1,2,3,...

// --------------------------------------------------------------------------- //

// 마스터 디비 사용하도록 설정.
$exchangeapi->set_db_link('master');

$symbols = explode(',', $symbols);
$orders = explode(',', $orders);
for( $i=0; $i<count($symbols); $i++) {
    $symbol = strtoupper($symbols[$i]);
    $order = $orders[$i];
    if($symbol && $order) {
        $sql = "UPDATE js_exchange_wallet SET order_no='{$exchangeapi->escape($order)}' WHERE userno='{$exchangeapi->escape($userno)}' AND  symbol='{$exchangeapi->escape($symbol)}' ";
        $exchangeapi->query($sql);
    }
}

// response
$exchangeapi->success(true);
