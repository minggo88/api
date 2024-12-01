<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

function sendSMS($to, $message) {
	// 한국 전화번호를 +82 형식으로 변환
	if (substr($to, 0, 3) == '010') {
		$to = '+82' . substr($to, 1); // 010 제거하고 +82 추가
	}

	$apiKey = 'f2b33afd';     // Nexmo API Key
    $apiSecret = 'xZOmlCRtz8QssuUs'; // Nexmo API Secret
		
	$url = 'https://rest.nexmo.com/sms/json';

	$data = [
		'from' => 'YOUR_BRAND_NAME', // 발신자 이름 (번호가 아니어도 됨)
		'text' => $message,
		'to' => $to,  // 수정된 수신자 번호
		'api_key' => $apiKey,
		'api_secret' => $apiSecret,
	];

	$options = [
		CURLOPT_URL => $url,
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => http_build_query($data),
		CURLOPT_RETURNTRANSFER => true,
	];

	$ch = curl_init();
	curl_setopt_array($ch, $options);
	
	$response = curl_exec($ch);
	
	if (curl_errno($ch)) {
		echo 'Error:' . curl_error($ch);
	} else {
		echo "Response: " . $response;
	}

	curl_close($ch);
}
	


session_start();
session_regenerate_id(); // 로그인할때마다 token 값을 바꿉니다.

$call = setDefault(loadParam('call'), '01039275103');
$message = setDefault(loadParam('message'), '테스트입니다.');


// --------------------------------------------------------------------------- //

// 마스터 디비 사용하도록 설정.

//sendSMS($call, $message);

// response

$r = $tradeapi->send_sms($call, $message);
if(!$r) {
	$tradeapi->error('210', $tradeapi->send_sms_error_msg);
}


// response
$tradeapi->success($r);

?>