<?php
$clientId = 'ef27cfaa-10c1-4470-adac-60ba476273f9';
$clientSecret = '83160c33-9045-4915-86d8-809473cdf5c3';

// API 엔드포인트
$apiUrl = 'https://sandbox.codef.io';

// 요청 헤더 설정
$headers = array(
    'Content-Type: application/json; charset=UTF-8',
    'Authorization: Basic '.base64_encode($clientId.':'.$clientSecret)
);

// 요청 바디 설정
$body = array(
    "organization": "0020",
    "connectedId": "3Lj7J-OvQub96",
    "account": "1002440000000",
    "startDate": "20190601",
    "endDate": "20190619",
    "orderBy": "0",
    "inquiryType": "1",
    "pageCount": "10"
);

// 요청 생성
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl.'/v1/kr/bank/b/account/transaction-list');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// 요청 실행
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// 응답 확인
if ($httpCode == 200) {
    echo 'API 요청 성공' . PHP_EOL;
    echo '응답: ' . $response . PHP_EOL;
} else {
    echo 'API 요청 실패 - HTTP 코드: ' . $httpCode . PHP_EOL;
    echo '에러 메시지: ' . $response . PHP_EOL;
}

// 연결 종료
curl_close($ch);
?>
