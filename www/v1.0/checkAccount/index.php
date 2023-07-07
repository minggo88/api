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

   //api처리내역


   //api최신화중 완료되지 않은 내용이 있다는 전재로 진행
   

   


   $data=[];
   $dataArray = [];
   for ($i = 0; $i < count($cnt); $i++) {
      //배열로 만들기
      $data = $currencies[$i];
      $dataArray = str_replace("'", "\"", $data); // 작은 따옴표를 큰 따옴표로 변환하여 유효한 JSON 형식으로 만듭니다.
      //$dataArray = json_decode($data, true);
      
      $tradeapi->error('049', __($dataArray));
      /*$txnid = $data['txnid'];
      $userno = $data['userno'];
      $name = $data['name'];
      $amount = $data['amount'];

      $result = $txnid."/".$userno."/".$name."/".$amount;
      
      $tradeapi->error('049', __($result));
      */
      //$amount = $currencies[$i].['amount'];

      //$sql2 = "SELECT * FROM js_income WHERE complteYN = 'N' AND js_income.resAccountDesc3 LIKE '%".$name."%' AND js_income.resAccountIn = '".$amount."';";   
   }

}



$tradeapi->error('049', __($data));


// get my member information
$r = $tradeapi->save_member_info($_REQUEST);

// response
$tradeapi->success($r);

?>