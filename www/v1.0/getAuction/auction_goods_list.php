<?php
/**
 * 경매 상품 조회(비회원용)
 */
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
// $tradeapi->checkLogin();

$userid = $tradeapi->get_login_userid();
// validate parameters
$goods_name  = setDefault($_REQUEST['goods_name'], '');
$added_trade_currency  = setDefault($_REQUEST['added_trade_currency'], '');
$page = checkNumber(setDefault($_REQUEST['page'], '1'));
$rows = checkNumber(setDefault($_REQUEST['rows'], '10'));
$start = ($page-1) * $rows;

// 슬레이브 디비 사용하도록 설정.
$tradeapi->set_db_link('slave');

// 모든 상품이 표시되고 상품의 옥션 정보가 보여지는 방식으로 변경.
$sql = "SELECT g.* FROM js_auction_goods g ";
if($added_trade_currency) {
	$sql.= "LEFT JOIN js_trade_currency tc ON g.idx=tc.symbol ";
}
$sql.= "WHERE 1 ";
if($goods_name) {
	$sql .= " AND g.title LIKE '%{$tradeapi->escape($goods_name)}%' ";
}
if($added_trade_currency=='Y') {
	$sql .= " AND tc.symbol IS NOT NULL ";
}
if($added_trade_currency=='N') {
	$sql .= " AND tc.symbol IS NULL ";
}
$sql .= " LIMIT {$start}, {$rows} ";
$payload = $tradeapi->query_list_object($sql);

// response
$tradeapi->success($payload);
