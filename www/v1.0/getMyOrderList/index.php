<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
$tradeapi->checkLogin();
$userno = $tradeapi->get_login_userno();

// validate parameters
$symbol = checkSymbol(strtoupper(checkEmpty($_REQUEST['symbol'], 'symbol')));
$exchange = checkSymbol(strtoupper(setDefault($_REQUEST['exchange'], $tradeapi->default_exchange)));
$orderid = checkNumber(setDefault($_REQUEST['orderid'], '0'));
$page = checkNumber(setDefault($_REQUEST['page'], '1'));
$start_date = $_REQUEST['start_date'];
if(isset($_REQUEST['start'])) {
    $page = $_REQUEST['start'] >0 ? 1 + $_REQUEST['start']/$_REQUEST['length'] : 1 ;
}
$rows = $_REQUEST['length'] ? $_REQUEST['length'] : checkNumber(setDefault($_REQUEST['rows'], '10'));
$return_type = checkRetrunType(strtolower(setDefault($_REQUEST['return_type'], 'JSON'))); // 구매 화폐
$trading_type = setDefault($_REQUEST['trading_type'], ''); // '' : all, 'B':구매, 'S': 판매, 'trade': 거래 limit 제거

// 슬레이브 디비 사용하도록 설정.
$tradeapi->set_db_link('slave');


$order_method = $_REQUEST['order'][0]['dir']=='desc' ? 'DESC' : 'ASC';
$order_column_no = $_REQUEST['order'][0]['column'];
$order_by = $_REQUEST['columns'][$order_column_no]['data'];


if ($symbol == "ALL") {
    $txns = $tradeapi->get_order_list_all($userno, 'all', $symbol, $exchange, $page, $rows, $orderid, $trading_type, $order_by, $order_method, $return_type, $start_date);
}else if($symbol == "TRADE"){
    $txns = $tradeapi->get_order_list_all($userno, 'trading', $symbol, $exchange, $page, $rows, $orderid, $trading_type, $order_by, $order_method, $return_type, $start_date);
} else {
    // check previos address
    if($trading_type == "trade"){
        $txns = $tradeapi->get_order_list($userno, 'all', $symbol, $exchange, $page, '1000', $orderid, $trading_type, $order_by, $order_method, $return_type, $start_date);    
    }else{
        $txns = $tradeapi->get_order_list($userno, 'all', $symbol, $exchange, $page, $rows, $orderid, $trading_type, $order_by, $order_method, $return_type, $start_date);
    }
    // var_dump($txns); exit;
}



// response
$tradeapi->success($txns, $return_type);
