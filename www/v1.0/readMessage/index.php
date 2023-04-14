<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

$tradeapi->checkLogin();

$receiver_userno      = $tradeapi->get_login_userno();

$r = $tradeapi->db_update("js_message", array('read_date'=>date('Y-m-d H:i:s')), array('receiver_userno'=>$receiver_userno, 'read_date'=>'0000-00-00 00:00:00') );
$_idx = $tradeapi->_recently_query['last_insert_id'];

if($r){
    $tradeapi->success($r);
}else{
    $tradeapi->error('005', __('A system error has occurred.'));
}