<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

$tradeapi->checkLogin();

$sender_userno        = $tradeapi->get_login_userno();
$receiver_userno      = checkNumber(checkEmpty($_REQUEST['receiver_userno'], '받는사람 회원번호'));
$message              = checkEmpty($_REQUEST['message'],'메시지');

$microtime      = $tradeapi->gen_id(9);
$sender_info    = $tradeapi->db_get_row('js_member', array('userno'=>$sender_userno));
$receiver_info  = $tradeapi->db_get_row('js_member', array('userno'=>$receiver_userno));

$data = array(
    'idx'               =>  $microtime,
    'sender_name'       =>  $sender_info->name,
    'sender_userno'     =>  $sender_info->userno,
    'receiver_name'     =>  $receiver_info->name,
    'receiver_userno'   =>  $receiver_info->userno,
    'message'           =>  $message,
    'reg_date'          =>  date('Y-m-d H:i:s'),
    'read_date'         =>  '0000-00-00 00:00:00'
);

$r = $tradeapi->db_insert('js_message', $data);
$_idx = $tradeapi->_recently_query['last_insert_id'];

if($r){
    $tradeapi->success(array('idx'=>$_idx));
}else{
    $tradeapi->error('005', __('A system error has occurred.'));
}