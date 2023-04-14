<?php
include dirname(__file__) . "/../../lib/ExchangeApi.php";

// 로그인 세션 확인.
$exchangeapi->checkLogin();
$userno = $exchangeapi->get_login_userno();

// validate parameters
$symbol = checkSymbol(strtoupper(checkEmpty($_REQUEST['symbol'], 'symbol')));
$txnid = checkNumber(setDefault($_REQUEST['txnid'], '0'));
$page = checkNumber(setDefault($_REQUEST['page'], '1'));
$rows = checkNumber(setDefault($_REQUEST['rows'], '10'));

// 슬레이브 디비 사용하도록 설정.
$exchangeapi->set_db_link('slave');

// check previos address
$txns = $exchangeapi->get_wallet_txn_list($symbol, $userno, $page, $rows, $txnid);

// response
$exchangeapi->success($txns);
