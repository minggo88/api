<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

function sendSMS($to, $message) {

	$sql = "SELECT guest_key FROM js_config_sms WHERE CODE = 'aligo'; ";
    $api_info = $tradeapi->query_fetch_object($sql);
	$accountSid = $api_info->guest_key;
	// 알리고 API 설정
	$api_url = "https://apis.aligo.in/send/"; // API 엔드포인트
	$api_key = $accountSid;               // 발급받은 API 키
	$sender = "01039275103";           // 발신자 번호 (인증된 발신번호여야 합니다)

    // 수신자 및 메시지 내용
	$receiver = $to; // 수신자 번호
	$message = $message; // 문자 내용

	// 요청 데이터
	$data = [
		'key' => $api_key,
		'user_id' => "YOUR_USER_ID", // 알리고 계정 ID
		'sender' => $sender,
		'receiver' => $receiver,
		'msg' => $message,
		'msg_type' => "SMS",         // SMS, LMS 선택 가능
		'title' => "",               // LMS일 경우 제목 (SMS는 빈값으로 유지)
		'destination' => "",         // 여러 수신자 발송 시 사용
		'rdate' => "",               // 예약 발송 날짜 (형식: YYYYMMDD)
		'rtime' => "",               // 예약 발송 시간 (형식: HHMM)
		'testmode_yn' => "N"         // 테스트 모드 여부 (Y 또는 N)
	];

	// cURL로 요청 전송
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $api_url);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);


    // 응답 처리
	$response = curl_exec($ch);
	if (curl_errno($ch)) {
		echo "cURL Error: " . curl_error($ch);
	} else {
		echo "Response: " . $response;
	}
}


$call = checkEmpty(loadParam('call'),'01039275103'); // 번호
$message = checkEmpty(loadParam('message'),'한글메시지입니다'); // 문자내역

// 문자 전송
sendSMS($call, $message);
?>
