<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
$tradeapi->checkLogin();
$userno = $tradeapi->get_login_userno();

$_REQUEST['userno'] = $userno;

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('master');

/***
 *    -------------api 연결 여기부터
 */

 $clientId = 'f5595264-2d91-4273-948c-0f4b6951beb2';
 $clientSecret = '0ad6e0f7-fa82-41e2-bf2c-9a53a9a9b7f7';
 
 // API 엔드포인트
 $apiUrl = 'https://development.codef.io';

 // 요청 헤더 설정
 $headers = array(
     'Content-Type: application/json; charset=UTF-8',
     'Authorization: Bearer '.base64_encode($clientId.':'.$clientSecret)
 );

 // 요청 바디 설정
 $body = array(
    "organization" => "0020",
    "connectedId" => "3Lj7J-OvQub96",
    "account" => "1002440000000",
    "startDate" => "20190601",
    "endDate" => "20190619",
    "orderBy" => "0",
    "inquiryType" => "1",
    "pageCount" => "10"
);


 // 요청 생성
 $ch = curl_init();
 
 curl_setopt($ch, CURLOPT_URL, $apiUrl.'v1/kr/bank/p/account/transaction-list');
 curl_setopt($ch, CURLOPT_POST, true);
 curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
 curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);



 // 요청 실행
 $response = curl_exec($ch);
 $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
 
 // 응답 확인
 if ($httpCode == 200) {
    //$tradeapi->error('ss', __('Please enter the order quantity below the remain quantity.')); //주문수량을 잔여수량 이하로 입력해주세요.
    $tradeapi->error('049', __('API 요청 성공'. $response)); //주문수량을 잔여수량 이하로 입력해주세요.
 } else {
    //$tradeapi->error('ff', __('qqqqq.')); //주문수량을 잔여수량 이하로 입력해주세요.
    $tradeapi->error('049', __('API 요청 실패'. $httpCode. '  //  '. $response)); //주문수량을 잔여수량 이하로 입력해주세요.

 }
 
 // 연결 종료
 curl_close($ch);

/***
 *  ----------------- 여기까지
 */

// get my member information
$r = $tradeapi->save_member_info($_REQUEST);

// response
$tradeapi->success($r);