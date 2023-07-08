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
   

   


   for ($i = 0; $i < count($cnt); $i++) {
      //배열로 만들기
      $data = $currencies[$i];
      $valueList = [];
      foreach ($data as $value) {
         $valueList[] = $value;
      }
      
      $txnid = $valueList[0];
      $userno = $valueList[1];
      $name = $valueList[2];
      $amount = $valueList[3];

      $result = $txnid."/".$userno."/".$name."/".$amount;
      
      //입금 내역확인
      $sql_income_search = "SELECT count(*) FROM js_income WHERE complteYN = 'N' AND js_income.resAccountDesc3 LIKE '%".$name."%' AND js_income.resAccountIn = '".$amount."';";
      $income_cnt = $tradeapi->query_one($sql);
      
      //입금 내역이 있으면 진행
      if($income_cnt>0){
         $currentDateTime = date('Y-m-d H:i:s');
         //income 업데이트
         $sql_income_search = "SELECT incomeIndex FROM js_income WHERE complteYN = 'N' AND js_income.resAccountDesc3 LIKE '%".$name."%' AND js_income.resAccountIn = '".$amount."' ORDER BY incomeIndex ASC LIMIT 1;";
         $incomeIndex = $tradeapi->query_one($sql_income_search);

         $sql_income_update = "UPDATE kkikda.js_income SET userno='".$userno."', complteYN='Y', txnindex='".$txnid."' WHERE incomeIndex='".$incomeIndex."';";
         $tradeapi->query_one($sql_income_update);

         //txn 업데이트
         $text = "자동 업데이트 imcomeIndex : ".$incomeIndex;
         $sql_txn_update = "UPDATE kkikda.js_exchange_wallet_txn SET txndate='".$currentDateTime."', status='D', msg='".$text."' WHERE txnid=".$txnid.";";
         $tradeapi->query_one($sql_txn_update);
         
         ///userno에 돈집어넣는 로직

         $sql_wallet_search = "SELECT confirmed FROM js_exchange_wallet WHERE userno = '".$userno."' AND symbol = 'KRW'";
         $confirmed = $tradeapi->query_one($sql_wallet_search);
         $confirmed = $confirmed + $amount;

         $sql_wallet_update = "UPDATE kkikda.js_exchange_wallet SET confirmed=".$confirmed." WHERE userno='.$userno.' AND symbol='KRW';";
         $tradeapi->query_one($sql_wallet_update);

         $tradeapi->error('049', __($sql_wallet_update));
      }
           
      
      //$amount = $currencies[$i].['amount'];

      //$sql2 = "SELECT * FROM js_income WHERE complteYN = 'N' AND js_income.resAccountDesc3 LIKE '%".$name."%' AND js_income.resAccountIn = '".$amount."';";   
   }

}






// get my member information
$r = $tradeapi->save_member_info($_REQUEST);

// response
$tradeapi->success($r);

?>