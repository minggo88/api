<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
$tradeapi->checkLogin();
$userno = $tradeapi->get_login_userno();

// validate parameters
$symbol = checkSymbol(strtoupper(checkEmpty($_REQUEST['symbol'], 'symbol')));
$exchange = checkSymbol(strtoupper(setDefault($_REQUEST['exchange'], $tradeapi->default_exchange)));
$txnid = checkNumber(setDefault($_REQUEST['txnid'], '0'));
$category = setDefault($_REQUEST['category'], '');
$page = checkNumber(setDefault($_REQUEST['page'], '1'));
$rows = checkNumber(setDefault($_REQUEST['rows'], '10'));
$start_date = checkDateFormat( setDefault($_REQUEST['start_date'], date('Y-m-d', time()) ) );
$end_data = checkDateFormat( setDefault($_REQUEST['end_date'], date('Y-m-d', time()) ) )." 23:59:59";

// 슬레이브 디비 사용하도록 설정.
$tradeapi->set_db_link('slave');

// check previos address
$txns = $tradeapi->get_my_trading_list($userno, $symbol, $exchange, $category, $page, $rows, $txnid, $start_date, $end_data);

// response
$tradeapi->success($txns);
