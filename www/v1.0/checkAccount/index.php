<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
$tradeapi->checkLogin();
$userno = $tradeapi->get_login_userno();

$_REQUEST['userno'] = $userno;


// 이미지 s3 정식폴더로 이동
$s3_check_param = array('image_identify_url', 'image_mix_url', 'image_bank_url');
foreach($s3_check_param as $param) {
    $file = $_REQUEST[$param];
    if($file && strpos($file, '.s3.')!==false && strpos($file, '/tmp/')!==false) {
        $_REQUEST[$param] = $tradeapi->move_tmpfile_to_s3($file);
    }
}

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('master');

/***
 * socket
 */
$ip = '61.109.249.165';
$port = 30576;
$message = "02000200XXXXXXXX200132015071110421423           023           0000002OY   74312391143                         88    0000000000100test                0000000000000                             088";

// TCP/IP 소켓 생성
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket === false) {
    $msg = "소켓 생성 실패: " . socket_strerror(socket_last_error());
    $tradeapi->error('100', '실패2 : '.$msg);
    exit;
}
$tradeapi->error('100', '실패3 : '.$msg);

// 서버에 연결
/*
$result = socket_connect($socket, $ip, $port);
if ($result === false) {
    $msg = "서버 연결 실패: " . socket_strerror(socket_last_error($socket));
    $tradeapi->error('100', '실패4 : '.$msg);
    exit;
}

// 서버로 메시지 전송
socket_write($socket, $message, strlen($message));

// 서버로부터 응답 받기
$response = socket_read($socket, 1024);
//echo "서버 응답: " . $response . PHP_EOL;

// 소켓 닫기
socket_close($socket);
*/
/***
 * socket close
 */

$r = array('message'=>$msg,'response'=>$msg )

$tradeapi->success($response);



/***
 *    -------------api 연결 여기부터
 */
/*
 $clientId = 'ef27cfaa-10c1-4470-adac-60ba476273f9';
 $clientSecret = '
 ';
 
 // API 엔드포인트
 $apiUrl = 'https://api.codef.io';

 // 요청 헤더 설정
 $headers = array(
     'Content-Type: application/json; charset=UTF-8',
     'Authorization: Basic '.base64_encode($clientId.':'.$clientSecret)
 );

 // 요청 바디 설정
 $body = array(
    "organization" => "0088",
    "connectedId" => "3Lj7J-OvQub96",
    "account" => "100035550510",
    "startDate" => "20230601",
    "endDate" => "20230612",
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

// get my member information
$r = $tradeapi->save_member_info($_REQUEST);

// response
$tradeapi->success($r);


/***
 *  ----------------- 여기까지
 */
