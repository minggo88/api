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

//토큰까지 확인됨

/***
 * connected ID 요청
 */
$urlPath = 'https://api.codef.io/v1/account/create';

$bodyMap = array();
$list = array();

$accountMap1 = array();
$accountMap1['countryCode'] = 'KR';
$accountMap1['businessType'] = 'BK';
$accountMap1['clientType'] = 'P';
$accountMap1['organization'] = '0003';
$accountMap1['loginType'] = '0';

//$password1 = '엔드유저의 인증서 비밀번호';
$password1 = '134679qa!@';
// RSAUtil.encryptRSA() 함수의 PHP 대체 방법을 사용해야 합니다.
// RSA 암호화를 위한 라이브러리나 함수를 사용하십시오.
//$accountMap1['password'] = $tradeapi->encryptRSA($password1, $publicKey);

//$accountMap1['keyFile'] = $tradeapi->encodeToFileString('/../../np/signPri.key');
//$accountMap1['derFile'] = $tradeapi->encodeToFileString('/../../np/signCert.der');
$list[] = $accountMap1;

$accountMap2 = array();
$accountMap2['countryCode'] = 'KR';
$accountMap2['businessType'] = 'BK';
$accountMap2['clientType'] = 'P';
$accountMap2['organization'] = '0020';
$accountMap2['loginType'] = '1';

$password2 = 'Rlrekrj1!';
// RSAUtil.encryptRSA() 함수의 PHP 대체 방법을 사용해야 합니다.
// RSA 암호화를 위한 라이브러리나 함수를 사용하십시오.
//$accountMap2['password'] = $tradeapi->encryptRSA($password2, $publicKey);

$accountMap2['id'] = 'flyminggo@naver.com ';
$accountMap2['birthday'] = '880719';
$list[] = $accountMap2;

$bodyMap['accountList'] = $list;

// CODEF API 호출
//$result = $tradeapi->apiRequest($urlPath, $bodyMap);



$tradeapi->error('049', __('커넥트ID : '. implode(" ", $bodyMap) )); //주문수량을 잔여수량 이하로 입력해주세요.








 // API 엔드포인트
 $apiUrl = 'https://development.codef.io';

 // 요청 헤더 설정
 /*$headers = array(
     'Content-Type: application/json; charset=UTF-8',
     'Authorization: Bearer '.base64_encode($clientId.':'.$clientSecret)
 );*/

 // 요청 바디 설정
 $body = array(
    "organization" => "0020",
    "connectedId" => "3Lj7J-OvQub96",
    "account" => "1002440000000",
    "startDate" => "20230601",
    "endDate" => "20230619",
    "orderBy" => "0",
    "inquiryType" => "1",
    "pageCount" => "10"
);


 // 요청 생성
 $ch = curl_init();
 
 curl_setopt($ch, CURLOPT_URL, $apiUrl.'/v1/kr/bank/p/account/transaction-list');
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

?>