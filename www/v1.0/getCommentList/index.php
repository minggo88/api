<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
// $tradeapi->checkLogin();
$userno = $tradeapi->get_login_userno();

// validate parameters
$link_idx = checkEmpty($_REQUEST['link_idx'],'link_idx');

$last_idx = checkNumber(setDefault($_REQUEST['last_idx'], 0));
$page = checkNumber(setDefault($_REQUEST['page'], '1'));
$rows = checkNumber(setDefault($_REQUEST['rows'], '20'));
// $limit = checkNumber(setDefault($_REQUEST['limit'], 20));
$start = ($page-1) * $rows;
if($last_idx) {$start = 0;}

// --------------------------------------------------------------------------- //

// 슬레이브 디비 사용하도록 설정.d
$tradeapi->set_db_link('slave');

$query = "SELECT idx, link_idx, userno, userid, bbscode, author, contents, ipaddr, thread, pos, like_cnt, regdate regtime, FROM_UNIXTIME(regdate) regdate, userno='{$userno}' my_comment  FROM js_bbs_comment WHERE warning_date IS NULL AND link_idx='{$tradeapi->escape($link_idx)}' ";
if($last_idx) { $query.= " AND idx<'{$tradeapi->escape($last_idx)}' "; }
$query.= " ORDER BY idx DESC LIMIT {$start}, {$rows}";
$c = $tradeapi->query_list_object($query);

// response
$tradeapi->success($c);
