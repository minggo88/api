<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
$tradeapi->checkLogin();
$userno = $tradeapi->get_login_userno();

$_REQUEST['userno'] = $userno;

$ip = '61.109.249.165';
$port = 30576;
$message = "02000200XXXXXXXX200132015071110421423           023           0000002OY   74312391143                         88    0000000000100test                0000000000000                             088";

// TCP/IP 소켓 생성
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket === false) {
    echo "소켓 생성 실패: " . socket_strerror(socket_last_error()) . PHP_EOL;
    exit;
}

// 서버에 연결
$result = socket_connect($socket, $ip, $port);
if ($result === false) {
    echo "서버 연결 실패: " . socket_strerror(socket_last_error($socket)) . PHP_EOL;
    exit;
}

// 서버로 메시지 전송
socket_write($socket, $message, strlen($message));

// 서버로부터 응답 받기
$response = socket_read($socket, 1024);
echo "서버 응답: " . $response . PHP_EOL;

// 소켓 닫기
socket_close($socket);

$tradeapi->success($response);
?>