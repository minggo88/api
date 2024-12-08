<?php
// 네이버 클라우드 API 정보
$access_key = 'ncp_iam_BPAMKR5woBlSwJixx7sJ'; // API Key ID
$secret_key = 'ncp_iam_BPKMKRDMqJ9oOv27HydJsfZh3QzWFCRcQ9';    // API Key
$service_url = 'https://api.ncloud.com/sms/v1/send'; // SMS 전송 API URL

// 발신번호와 수신번호, 메시지 내용
$from = '01039275103'; // 예: '01012345678'
$to = '01039275103';   // 예: '01087654321'
$message = '여기에 메시지 내용 작성'; // 보내고자 하는 메시지

// 요청 헤더 설정
$headers = [
    'X-NCP-APIGW-API-KEY-ID' => $access_key,
    'X-NCP-APIGW-API-KEY' => $secret_key,
    'Content-Type' => 'application/json'
];

// 요청 바디 데이터 설정
$data = [
    'from' => $from,
    'to' => $to,
    'content' => $message
];

// cURL을 사용한 API 호출
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $service_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// 에러 처리
if(curl_errno($ch)) {
    echo 'cURL error: ' . curl_error($ch);
}

curl_close($ch);

// 결과 출력
if ($http_code == 200) {
    echo "문자가 성공적으로 전송되었습니다!";
} else {
    echo "문자 전송 실패! HTTP 상태 코드: " . $http_code . " 응답: " . $response;
}
?>