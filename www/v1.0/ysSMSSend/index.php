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
	$sms['user_id'] = "ngng123"; // SMS 아이디
	$sms['key'] = $accountSid;//인증키
	$sender = "01039275103";           // 발신자 번호 (인증된 발신번호여야 합니다)

    // 수신자 및 메시지 내용
	$sms['msg'] = $message;
	$sms['receiver'] = $to;
	$sms['destination'] = '';
	$sms['sender'] = $sender;

	/*****/
	$host_info = explode("/", $sms_url);
	$port = $host_info[0] == 'https:' ? 443 : 80;

	$oCurl = curl_init();
	curl_setopt($oCurl, CURLOPT_PORT, $port);
	curl_setopt($oCurl, CURLOPT_URL, $sms_url);
	curl_setopt($oCurl, CURLOPT_POST, 1);
	curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($oCurl, CURLOPT_POSTFIELDS, $sms);
	curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
	$ret = curl_exec($oCurl);
	curl_close($oCurl);

	echo $ret;
	$retArr = json_decode($ret);
    
}


$call = checkEmpty(loadParam('call'),'01039275103'); // 번호
$message = checkEmpty(loadParam('message'),'한글메시지입니다'); // 문자내역

// 문자 전송
sendSMS($call, $message);
?>
