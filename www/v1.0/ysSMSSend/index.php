<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

$msg = "[{$config_basic->shop_ename}] ".__('Click the following link to set a new password.')." {$domain}/repw.html?t={$tmp_pw}";
$r = $tradeapi->send_sms('01039275103', $msg);
if(!$r) {
	$tradeapi->error('210', $tradeapi->send_sms_error_msg);
}


$r['msg'] = '';
// 결과 출력
if ($http_code == 200) {
    $text = "문자가 성공적으로 전송되었습니다!";
	$r['msg'] = 'check : '.$text;
} else {
	$r['msg'] = "문자 전송 실패! HTTP 상태 코드: " . $http_code . " 응답: " . $response;
}

$tradeapi->success($r);
?>