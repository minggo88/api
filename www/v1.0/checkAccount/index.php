<?php
include dirname(__file__) . "/../../lib/TradeApi.php";
$tradeapi->checkLogin();
$userno = $tradeapi->get_login_userno();

$tradeapi->set_logging(true);
$tradeapi->set_log_dir($tradeapi->log_dir.'/'.basename(__dir__).'/');
$tradeapi->set_log_name('');
$tradeapi->write_log("REQUEST: " . json_encode($_REQUEST));

$res = "";

// 접속할 IP와 포트를 지정합니다.
$ip = '61.109.249.165';
$port = 30576;

// TCP/IP 소켓을 엽니다.
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

// 소켓이 열리지 않으면 오류를 출력합니다.
if ($socket === false) {
    $res =  "socket_create() failed: " . socket_strerror(socket_last_error()) . "\n";
}

// 서버에 연결합니다.
$result = socket_connect($socket, $ip, $port);

// 연결이 실패하면 오류를 출력합니다.
if ($result === false) {
    echo "socket_connect() failed: " . socket_strerror(socket_last_error($socket)) . "\n";
}

// 서버로 데이터를 전송합니다.
$message = "02000200XXXXXXXX200132015071110421423           023           0000002OY   74312391143                         88    0000000000100test                0000000000000                             088";
socket_write($socket, $message, strlen($message));

// 서버로부터 응답을 받습니다.
$response = socket_read($socket, 1024);

// 서버로부터 받은 응답을 출력합니다.
$res = "Response from server: " . $response . "\n";

// 소켓을 닫습니다.
socket_close($socket);


// response
$tradeapi->success($res);

?>