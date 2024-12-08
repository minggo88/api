<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

function sendSMS($to, $message) {
    // 한국 전화번호를 +82 형식으로 변환
    if (substr($to, 0, 3) == '010') {
        $to = '+82' . substr($to, 1); // 010 제거하고 +82 추가
    }

    $apiKey = 'f2b33afd';     // Nexmo API Key
    $apiSecret = 'xZOmlCRtz8QssuUs'; // Nexmo API Secret

    // 메시지 본문을 UTF-8로 인코딩 (한글 깨짐 방지)
    $message = mb_convert_encoding($message, "UTF-8", "auto");

    $url = 'https://rest.nexmo.com/sms/json';

    $data = [
        'from' => 'YOUR_BRAND_NAME', // 발신자 이름 (번호가 아니어도 됨)
        'text' => $message,
        'to' => $to,  // 수정된 수신자 번호
        'api_key' => $apiKey,
        'api_secret' => $apiSecret,
        'type' => 'unicode'  // 한글 메시지 전송을 위한 설정
    ];

    $options = [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        // HTTP 헤더에 UTF-8 인코딩 추가
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8'
        ],
    ];

    $ch = curl_init();
    curl_setopt_array($ch, $options);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        $r['msg'] = 'Error:' . curl_error($ch);
        echo 'Error:' . curl_error($ch);
        $tradeapi->error('210', $r);
    } else {
        // 응답 확인
        echo "Response: " . $response . $message;
        $r['msg'] = "Response: " . $response . $message;
        $tradeapi->success($r);
    }

    curl_close($ch);
}

session_start();
session_regenerate_id(); // 로그인할때마다 token 값을 바꿉니다.

$call = setDefault(loadParam('call'), '01039275103');
$message = setDefault(loadParam('message'), '테스트입니다.');  // 한글 메시지 확인

// 문자 전송
sendSMS($call, $message);

// response
/*
$r = $tradeapi->send_sms($call, $message);
if(!$r) {
    $tradeapi->error('210', $tradeapi->send_sms_error_msg);
}
*/

$tradeapi->success($r);
?>
