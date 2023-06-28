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
 
//토큰수령

$url = "https://oauth.codef.io/oauth/token";
$params = "grant_type=client_credentials&scope=read";

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
$scope = 'oob'; // 스코프 (oob: 인증 및 연결 동의, inquiry: 조회, transfer: 이체 등)

// 인증 정보를 사용하여 Access Token을 요청
$authUrl = 'https://oauth.codef.io/oauth/token';
$authData = array(
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'scope' => $scope,
);
$authOptions = array(
    'http' => array(
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode($authData),
    ),
);
$authContext = stream_context_create($authOptions);
$authResult = file_get_contents($authUrl, false, $authContext);
$authResponse = json_decode($authResult, true);
$accessToken = $authResponse['access_token'];

// Access Token을 사용하여 connectedId 발급 요청
$connectedIdUrl = 'https://api.codef.io/v1/account/connectedId';
$connectedIdData = array(
    'organization' => $serviceType,
);
$connectedIdOptions = array(
    'http' => array(
        'method' => 'POST',
        'header' => 'Content-Type: application/json' . PHP_EOL .
                    'Authorization: Bearer ' . $accessToken,
        'content' => json_encode($connectedIdData),
    ),
);
$connectedIdContext = stream_context_create($connectedIdOptions);
$connectedIdResult = file_get_contents($connectedIdUrl, false, $connectedIdContext);
$connectedIdResponse = json_decode($connectedIdResult, true);
$connectedId = $connectedIdResponse['connectedId'];

$tradeapi->error('049', __('API 요청 성공'. $connectedId)); //주문수량을 잔여수량 이하로 입력해주세요.








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