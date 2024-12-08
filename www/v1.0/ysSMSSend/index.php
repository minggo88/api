<?php
include dirname(__file__) . "/../../lib/TradeApi.php";




function sendSMS($to, $message) {

	$sql = "SELECT tran_callback, guest_no, guest_key FROM js_config_sms WHERE CODE = 'YS'; ";
    $api_info = $tradeapi->query_fetch_object($sql);

    // Twilio 계정 정보
    $accountSid = $api_info->guest_no; // Twilio Account SID
    $authToken = $api_info->guest_key;   // Twilio Auth Token
    $from = $api_info->tran_callback;          // Twilio에서 인증된 발신 번호

    // 한국 전화번호를 +82 형식으로 변환
    if (substr($to, 0, 3) == '010') {
        $to = '+82' . substr($to, 1); // 010 제거하고 +82 추가
    }

    // Twilio 클라이언트 초기화
    $twilio = new Client($accountSid, $authToken);

    try {
        // 문자 메시지 전송
        $messageResponse = $twilio->messages->create(
            $to, // 수신자 번호
            [
                'from' => $from, // 인증된 Twilio 발신 번호
                'body' => $message // 메시지 내용
            ]
        );

        // 성공적으로 전송된 메시지 SID 출력
        echo "Message sent successfully! SID: " . $messageResponse->sid;
    } catch (Exception $e) {
        // 오류 처리
        echo "Error: " . $e->getMessage();
    }
}


$call = checkEmpty(loadParam('call'),'01039275103'); // 번호
$message = checkEmpty(loadParam('message'),'한글메시지입니다'); // 문자내역

// 문자 전송
sendSMS($call, $message);
?>
