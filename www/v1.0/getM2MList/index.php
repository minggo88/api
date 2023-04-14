<?php
/**
 * 1:1 문의 게시판 목록
 */
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
$tradeapi->checkLogin();
$userno = $tradeapi->get_login_userno(); 

// validate parameters
$last_idx = checkNumber(setDefault($_REQUEST['last_idx'], 0));
$page = checkNumber(setDefault($_REQUEST['page'], '1'));
$rows = checkNumber(setDefault($_REQUEST['rows'], '10'));
$start = ($page-1) * $rows;
if($last_idx) {$start = 0;}

// --------------------------------------------------------------------------- //

// 슬레이브 디비 사용하도록 설정.
$tradeapi->set_db_link('slave');

$sitecode = $tradeapi->get_site_code();

$query = "SELECT COUNT(idx) FROM js_mtom WHERE sitecode='{$tradeapi->escape($sitecode)}' AND userno='{$tradeapi->escape($userno)}'  ";
$total = $tradeapi->query_one($query);

$query = "SELECT * FROM js_mtom WHERE sitecode='{$tradeapi->escape($sitecode)}' AND userno='{$tradeapi->escape($userno)}' ";
if($last_idx) { $query.= " AND idx<'{$tradeapi->escape($last_idx)}' "; }
$query.= "ORDER BY regdate DESC LIMIT {$tradeapi->escape($start)}, {$tradeapi->escape($rows)}";
$c = $tradeapi->query_list_object($query);

// response
$tradeapi->success(array('data'=>$c, 'total'=>$total));
