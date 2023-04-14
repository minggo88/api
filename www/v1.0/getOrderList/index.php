<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
// $tradeapi->checkLogin();
// $userno = $tradeapi->get_login_userno();

// validate parameters
$symbol = checkSymbol(strtoupper(checkEmpty($_REQUEST['symbol'], 'symbol')));
$exchange = checkSymbol(strtoupper(setDefault($_REQUEST['exchange'], $tradeapi->default_exchange)));
$trading_type = setDefault($_REQUEST['trading_type'], '');
$trading_type = $trading_type ? ( $trading_type == 'buy' ? 'B' : 'S' ) : ''; // change to db value
$orderid = checkNumber(setDefault($_REQUEST['orderid'], '0'));
$page = checkNumber(setDefault($_REQUEST['page'], '1'));
$rows = checkNumber(setDefault($_REQUEST['rows'], '10'));
$status = setDefault($_REQUEST['status'], 'all');

$order_by = setDefault($_REQUEST['order_by'], 'orderid');
$order_method = setDefault($_REQUEST['order_method'], 'desc');
// dataTables 정렬 선언 대응
if($_REQUEST['order']) {
    // 첫번째 정렬만 사용
    $order_by = $_REQUEST['columns'][$_REQUEST['order'][0]['column']]['data'];
    $order_method = $_REQUEST['order'][0]['dir'];
    // 여러 정렬 사용
    // $i = 0;
    // foreach ($_REQUEST['order'] as $order) {
    //     $_GET['sort_target'][$i] = $_REQUEST['columns'][$order['column']]['data'];
    //     $_GET['sort_method'][$i] = $order['dir'];
    //     $i++;
    // }
}

// 슬레이브 디비 사용하도록 설정.
$tradeapi->set_db_link('slave');

// check previos address
$txns = $tradeapi->get_order_list('', $status, $symbol, $exchange, $page, $rows, $orderid, $trading_type, $order_by, $order_method);

// response
$tradeapi->success($txns);
