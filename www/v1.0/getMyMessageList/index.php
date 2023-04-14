<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

$tradeapi->checkLogin();

$last_idx = setDefault($_REQUEST['last_idx'], 0);
$page = checkNumber(setDefault($_REQUEST['page'], '1'));
$rows = checkNumber(setDefault($_REQUEST['rows'], '10'));
$start = ($page-1) * $rows;
if($last_idx) {$start = 0;}

$receiver_userno      = $tradeapi->get_login_userno();

$data['totel_count']  = count($tradeapi->db_get_list('js_message', array('receiver_userno'=>$receiver_userno)));
$data['unread_count'] = count($tradeapi->db_get_list('js_message', array('receiver_userno'=>$receiver_userno, 'read_date'=>'0000-00-00 00:00:00')));

$query = "SELECT *  FROM js_message WHERE  receiver_userno={$tradeapi->escape($receiver_userno)} ";
if($last_idx) { $query.= " AND idx<='{$tradeapi->escape($last_idx)}' "; }
$query.= " ORDER BY idx DESC LIMIT {$start}, {$rows}";

$data['list'] = $tradeapi->query_list_object($query);

$tradeapi->success($data);