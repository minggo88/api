<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
$tradeapi->checkLogin();
$userno = $tradeapi->get_login_userno();

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('master');

$sql = "SELECT COUNT(*) FROM js_exchange_wallet_txn WHERE symbol = 'KRW' AND status = 'O';";

$cnt = $tradeapi->query_one($sql);

if($cnt >0){
   $sql = "SELECT txnid,userno,address_relative,amount FROM js_exchange_wallet_txn WHERE symbol = 'KRW' AND status = 'O';";
   $currencies = $tradeapi->query_list_object($sql);


}



$tradeapi->error('049', __($currencies));


// get my member information
$r = $tradeapi->save_member_info($_REQUEST);

// response
$tradeapi->success($r);

?>