<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
// $tradeapi->checkLogin();
// $userno = $tradeapi->get_login_userno();

// validate parameters
if(is_array($_REQUEST['symbol'])) { // 배열로 잘못들어오는 경우가 있어서 배열로 들어오면 CSV 문자열로 바꿉니다.
    $_REQUEST['symbol'] = implode(',', $_REQUEST['symbol']);
}
$symbol = checkSymbol(strtoupper(setDefault($_REQUEST['symbol'], 'ALL')));
$exchange = checkSymbol(strtoupper(setDefault($_REQUEST['exchange'], $tradeapi->default_exchange)));
$cnt = checkNumber(setDefault($_REQUEST['cnt'], 5));
$goods_grade = setDefault($_REQUEST['goods_grade'], 'A');

// --------------------------------------------------------------------------- //

// 슬레이브 디비 사용하도록 설정.
$tradeapi->set_db_link('slave');

// 여러 가격을 알려고 할때.
if(strpos($symbol, ',')!==false) {
    $symbol = explode(',', $symbol);
}
// 전체 조회시.
if($symbol=='ALL') {
    $symbol = '';
}
// 인기 종목 조회
if($symbol=='HOT') {
    $symbol = $tradeapi->query_one("SELECT GROUP_CONCAT(symbol) FROM ( SELECT symbol FROM js_trade_price WHERE symbol IN (SELECT symbol FROM js_trade_currency WHERE active='Y') ORDER BY volume DESC, price_close DESC LIMIT {$cnt} ) t ");
    $symbol = explode(',', $symbol);
}
// 고가 종목 조회
if($symbol=='HIGH') {
    $symbol = $tradeapi->query_one("SELECT GROUP_CONCAT(symbol) FROM ( SELECT symbol FROM js_trade_price WHERE symbol IN (SELECT symbol FROM js_trade_currency WHERE active='Y') ORDER BY price_close DESC LIMIT {$cnt} ) t ");
    $symbol = explode(',', $symbol);
}
// 저가 종목 조회
if($symbol=='LOW') {
    $symbol = $tradeapi->query_one("SELECT GROUP_CONCAT(symbol) FROM ( SELECT symbol FROM js_trade_price WHERE symbol IN (SELECT symbol FROM js_trade_currency WHERE active='Y') ORDER BY price_close ASC LIMIT {$cnt} ) t ");
    $symbol = explode(',', $symbol);
}
// var_dump($symbol); 

// check wallet owner
$currency = $tradeapi->get_spot_price($symbol, $exchange, $goods_grade);
// var_dump($currency); exit;

// $currency 값 $symbol 순서대로 재정렬 ... order by  귀찮아서 ...
if(strtoupper($_REQUEST['symbol']) == 'HOT'||strtoupper($_REQUEST['symbol']) == 'HIGH'||strtoupper($_REQUEST['symbol']) == 'LOW') {
    $tmp = array();
    foreach($symbol as $s ){
        foreach($currency as $c) {
            if($s == $c->symbol) {
                $tmp[] = $c;
                break;
            }
        }
    }
    $currency = $tmp;

}

// response
$tradeapi->success($currency);
