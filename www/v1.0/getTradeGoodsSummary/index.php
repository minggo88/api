<?php
/**
 * 거래소 상품의 요약정보를 리턴합니다.
 */
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
// $tradeapi->checkLogin();
$userno = $tradeapi->get_login_userno();

// validate parameters
// $symbol = checkSymbol(strtoupper(setDefault($_REQUEST['symbol'], 'ALL')));
// $name = setDefault($_REQUEST['name'], '');
// $cal_base_price = setDefault($_REQUEST['cal_base_price'], '');
// $getNFTData = setDefault($_REQUEST['getNFTData'], 'Y');

// --------------------------------------------------------------------------- //

// 슬레이브 디비 사용하도록 설정.
$tradeapi->set_db_link('slave');

$r = array(
    'cnt_tea'=>0,
    'cnt_teaware'=>0,
    'cnt_totaltea'=>0
);
// cnt_tea 거래소 상품수
$r['cnt_tea'] = $tradeapi->query_one("SELECT COUNT(*) FROM js_trade_currency WHERE active='Y' AND tradable='Y'") * 1;
$r['cnt_totaltea'] = $tradeapi->query_one("SELECT SUM(confirmed) FROM js_exchange_wallet WHERE userno>0 AND symbol IN (SELECT symbol FROM js_trade_currency WHERE active='Y')") * 1;


// response
$tradeapi->success($r);
