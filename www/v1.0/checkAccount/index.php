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
   /***
    *    -------------api 연결 여기부터
    */

   $clientId = 'f5595264-2d91-4273-948c-0f4b6951beb2';
   $clientSecret = '0ad6e0f7-fa82-41e2-bf2c-9a53a9a9b7f7';
   $publicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAsZIbPm4TtJ3kz4fm0v2SdJHN5ej5sCxL1PVLcVo55p+K8ivhvUnzaM0a0vfcxVaBN5q4aQMKXkWVZ0YqFQxFGPl9lJ/ndbY4mBLWIvBsA7U9NN4UFwSbuEdL7TYN9gPhKyyA/5ntSB9E0k6lH43aa1eyRaY+Q6SG+OwJxueib/A3uO+KDKOTClW9rzXbA1/5gwe0R1rBRj6FBMWo+qXfF/+8LPveOu9PMn9W5xboQ4/DvIUyTTroIfl26x/Kb/o5TXgbidSSTUhPzwNTSAvO6gxhVM+jD1Sq8qECJtMrE+DzT4faqv+O2IyfB42dlJ22BcaHZdsRGsVt57xsrO0wKwIDAQAB';
   
   //토큰수령

   $url = "https://oauth.codef.io/oauth/token";
   $params = "grant_type=client_credentials&scope=read";
   $token2 = "";//accessToken

   $con = curl_init($url);
   curl_setopt($con, CURLOPT_POST, true);
   curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
   curl_setopt($con, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded"));

   $auth = $clientId . ":" . $clientSecret;
   $authEncBytes = base64_encode($auth);
   $authHeader = "Basic " . $authEncBytes;

   curl_setopt($con, CURLOPT_HTTPHEADER, array("Authorization: " . $authHeader));
   curl_setopt($con, CURLOPT_POSTFIELDS, $params);

   $response = curl_exec($con);
   $responseCode = curl_getinfo($con, CURLINFO_HTTP_CODE);
   curl_close($con);

   $headers = array();

   if ($responseCode == 200) {
         $tokenMap = json_decode(urldecode($response), true);
         //$tradeapi->error('049', __('토큰확인'. var_dump($tokenMap)));
         $token = implode(" ", $tokenMap);
         //$token = "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJzZXJ2aWNlX3R5cGUiOiIxIiwic2NvcGUiOlsicmVhZCJdLCJzZXJ2aWNlX25vIjoiMDAwMDAyNDk3MDAyIiwiZXhwIjoxNjg4NTM1NDE1LCJhdXRob3JpdGllcyI6WyJJTlNVUkFOQ0UiLCJQVUJMSUMiLCJCQU5LIiwiRVRDIiwiU1RPQ0siLCJDQVJEIl0sImp0aSI6IjkyNTJmMDRiLTgyN2YtNDAxMS05ODgxLTQxZGRhYzcxMTMxYyIsImNsaWVudF9pZCI6ImY1NTk1MjY0LTJkOTEtNDI3My05NDhjLTBmNGI2OTUxYmViMiJ9.ETcfmQ-oUiw7zayFlk1roXQ8j-gUVoZPfpJ7IoZ91MdK6te3sb-K8d2GqJ6qk8XEO-ee-8vblIUPyxFzewJOvsBLc7VBIl8keArjFfnus5l2VBmvDpwVFkJflMteaF8IKww9U7hRqqFWlt8Lz1MhQaZN1QpeeaDCH-3wGgpA432Bsw99X3e3gIt-dsxU98eZ1E2F_R9s5xSHCs5G2wXTqnMaMRzuuGCVEgrxupeXHBPw008EHauJa29LceSxAeFkYfCC1qOlYQTEAHbva1ireeW02zhcTe9sFE9Dr4AUrtouOa5gCOZ5WRgID27QBpH1Jkpww-xz0p-IJ5s-7h98jA";
         $accesstoken = explode(' ', $token);
         $token2 = $accesstoken[0];
         //$tradeapi->error('049', __('토큰확인'. $token.' / '.$token2));

         // 요청 헤더 설정
         $headers = array(
            'Content-Type: application/json; charset=UTF-8',
            'Authorization: Bearer '.$token2
         );
         //$tradeapi->error('049', __('헤더확인'. implode(" ", $headers)));
   } else {
         $tradeapi->error('049', __('토큰실패'. $responseCode));
   }

    // API 엔드포인트
   $apiUrl = 'https://development.codef.io';

   // 요청 바디 설정
   $password1 = 'Dpszlfndi1!';
   $pw = $tradeapi->encryptRSA($password1, $publicKey);
   $account = '23891002273004';

   /***
    * password 복호화
    */
   $key = $tradeapi->search_kkikdageo();

   $tagetData = file_get_contents(dirname(__FILE__).'/../../np/sk.bin');

   // 복호화
   $decryptedData = openssl_decrypt($tagetData, 'AES-256-CBC', $key, 0, '1234567890123456');
   $acpw = $tradeapi->encryptRSA($decryptedData, $publicKey);

   //날자 만들기
   $year = date('Y'); // 현재 연도
   $month = date('m'); // 현재 월
   $firstDayOfMonth = $year . $month . '01';
   $today = date('Ymd');

   //1일일경우 전달과 비교
   if ($firstDayOfMonth === $today) {
      if ($month === '01') {
          // 1월 1일인 경우 전년도 12월 25일로 $firstDayOfMonth을 변경합니다
          $year = $year - 1;
          $firstDayOfMonth = $year . '1225';
      } else {
          // 그 외의 경우 전달의 25일로 $firstDayOfMonth을 변경합니다
          $previousMonth = sprintf('%02d', $month - 1);
          $firstDayOfMonth = $year . $previousMonth . '25';
      }
  }

   $body = array(
      "organization" => "0081",
      "fastId" => $account,
      "fastPassword" => $acpw,
      "id" => "KKIKDA2021",
      "password" => $pw,
      "account" => $account,
      "accountPassword" => $acpw,
      "startDate" => $firstDayOfMonth,
      "endDate" => $today,
      "orderBy" => "0",
      "identity" => "6238602033"
   );

   // 요청 생성
   $ch = curl_init();
   
   curl_setopt($ch, CURLOPT_URL, $apiUrl.'/v1/kr/bank/b/fast-account/transaction-list');
   curl_setopt($ch, CURLOPT_POST, true);
   curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
   curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);



   // 요청 실행
   $response = curl_exec($ch);
   $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
   
   // 응답 확인
   if ($httpCode == 200) {
      $decodedData = urldecode($response);
      $data = json_decode($decodedData, true);
      $apiArray = [];
      // result 데이터
      $apiArray = $data['data']['resTrHistoryList'];

      for ($i = count($apiArray) - 1; $i >= 0; $i--) {
         $resAccountTrDate = $apiArray[$i]['resAccountTrDate'];
         $resAccountTrTime = $apiArray[$i]['resAccountTrTime'];
         $resAccountOut = $apiArray[$i]['resAccountOut'];
         $resAccountIn = $apiArray[$i]['resAccountIn'];
         $resAccountDesc1 = $apiArray[$i]['resAccountDesc1'];
         $resAccountDesc2 = $apiArray[$i]['resAccountDesc2'];
         $resAccountDesc3 = $apiArray[$i]['resAccountDesc3'];
         $resAccountDesc4 = $apiArray[$i]['resAccountDesc4'];
         $resAfterTranBalance = $apiArray[$i]['resAfterTranBalance'];

         $sql_income_api_search = "SELECT COUNT(*) FROM js_income WHERE resAccountDesc2 LIKE '%".$resAccountDesc2."%' AND resAccountDesc3 LIKE '%".$resAccountDesc3."%' AND resAccountDesc4 LIKE '%".$resAccountDesc4."%'";
         $sql_income_api_search = $sql_income_api_search." AND resAccountIn = '".$resAccountIn."' AND resAfterTranBalance = '".$resAfterTranBalance."' AND resAccountTrDate = '".$resAccountTrDate."' AND resAccountTrTime = '".$resAccountTrTime."';";

         $cnt = $tradeapi->query_one($sql_income_api_search);

         if($cnt <1){
            $sql_income_insert = "INSERT INTO kkikda.js_income (resAccountDesc1, resAccountDesc2, resAccountDesc3, resAccountDesc4, resAccountIn, resAccountOut, resAccountTrDate, resAccountTrTime, resAfterTranBalance, complteYN)
            VALUES('".$resAccountDesc1."', '".$resAccountDesc2."', '".$resAccountDesc3."', '".$resAccountDesc4."', '".$resAccountIn."', '".$resAccountOut."', '".$resAccountTrDate."', '".$resAccountTrTime."', '".$resAfterTranBalance."', 'N');
            ";
            $cnt2 = $tradeapi->query_one($sql_income_insert);
         }
      }
      // data 데이터
      //$tradeapi->error('049', __($sql_income_api_search)); //주문수량을 잔여수량 이하로 입력해주세요.
   } else {
      //$tradeapi->error('ff', __('qqqqq.')); //주문수량을 잔여수량 이하로 입력해주세요.
      $tradeapi->error('049', __('API 요청 실패'. $httpCode. '  //  '. $response)); //주문수량을 잔여수량 이하로 입력해주세요.

   }
   
   // 연결 종료
   curl_close($ch);

   /***
    *  ----------------- 여기까지
    */

   //api최신화중 완료되지 않은 내용이 있다는 전재로 진행
   

   


   for ($i = 0; $i < $cnt; $i++) {
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

         $sql_wallet_update = "UPDATE kkikda.js_exchange_wallet SET confirmed=".$confirmed." WHERE userno='".$userno."' AND symbol='KRW';";
         
         $tradeapi->query_one($sql_wallet_update);

         //$tradeapi->error('049', __($sql_wallet_update));
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