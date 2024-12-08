<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

function sendSMS($to, $message) {
	global $tradeapi;

	$sql = "SELECT guest_key FROM js_config_sms WHERE CODE = 'aligo'; ";
    $api_info = $tradeapi->query_fetch_object($sql);

	if (!$api_info || empty($api_info->guest_key)) {
        die("API 인증키를 가져오지 못했습니다.");
    }

	$accountSid = $api_info->guest_key;
	// 알리고 API 설정
    $sms_url = "https://apis.aligo.in/send/"; // 전송요청 URL
    $sms = [
        'user_id' => "ngng123",           // SMS 아이디
        'key' => $accountSid,            // 인증키
        'sender' => "01039275103",       // 발신자 번호 (인증된 발신번호여야 합니다)
        'receiver' => $to,               // 수신자 번호
        'destination' => '',             // 목적지 정보
        'msg' => $message,               // 메시지 내용
        'msg_type' => 'SMS',             // 메시지 타입 (SMS, LMS, MMS)
    ];

    // cURL로 요청 전송
    $oCurl = curl_init();
    curl_setopt($oCurl, CURLOPT_URL, $sms_url);
    curl_setopt($oCurl, CURLOPT_POST, true);
    curl_setopt($oCurl, CURLOPT_POSTFIELDS, http_build_query($sms));
    curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($oCurl);

    if (curl_errno($oCurl)) {
        echo 'cURL Error: ' . curl_error($oCurl);
    }

    curl_close($oCurl);

    // 응답 처리
    $response_data = json_decode($response, true);

    if ($response_data['result_code'] === '1') {
        echo "문자 발송 성공: " . $response_data['message'];
    } else {
        echo "문자 발송 실패: " . $response_data['message'];
    }
}


// 유틸리티 함수
function checkEmpty($value, $default) {
    return !empty($value) ? $value : $default;
}

function loadParam($key) {
    return isset($_REQUEST[$key]) ? $_REQUEST[$key] : null;
}

// 사용자 입력값 처리
$call = checkEmpty(loadParam('call'), '01039275103'); // 번호
$message = checkEmpty(loadParam('message'), '한글 메시지입니다'); // 문자내역

// 문자 전송
sendSMS($call, $message);
?>
