<?php
include dirname(__file__) . "/../../lib/ExchangeApi.php";

function sendSMS($to, $message) {
    // 한국 전화번호를 +82 형식으로 변환
    if (substr($to, 0, 3) == '010') {
        $to = '+82' . substr($to, 1); // 010 제거하고 +82 추가
    }

    $apiKey = 'f2b33afd';     // Nexmo API Key
    $apiSecret = 'xZOmlCRtz8QssuUs'; // Nexmo API Secret
    $from = '+821039275103'; // 내 번호를 발신자로 설정 (형식: +82XXXXXXXXXX)

    // 메시지 본문을 UTF-8로 인코딩 (한글 깨짐 방지)
    $message = mb_convert_encoding($message, "UTF-8", "auto");

    $url = 'https://rest.nexmo.com/sms/json';

    $data = [
        'from' => $from, // 내 번호를 발신자로 설정
        'text' => $message,
        'to' => $to,  // 수신자 번호
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
        echo 'Error:' . curl_error($ch);
    } else {
        // 응답 확인
        echo "Response: " . $response;
    }

    curl_close($ch);
}

$call = setDefault(loadParam('call'), '01039275103');
$message = setDefault(loadParam('message'), '한글메시지입니다.');  // 한글 메시지 확인


// 문자 전송
sendSMS($call, $message);
?>
