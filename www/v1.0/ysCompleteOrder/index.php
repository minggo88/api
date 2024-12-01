<?php
include dirname(__file__) . "/../../lib/TradeApi.php";
$exchangeapi->token = session_create_id();

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

// if($_SERVER['REMOTE_ADDR']!='61.74.240.65') {$exchangeapi->error('001','시스템 정검중입니다.');}
$tradeapi->set_db_link('slave');

// -------------------------------------------------------------------- //


// 거래소 api는 토큰을 전달 받을때만 작동하도록 되어 있어서 로그인시 token을 생성해 줍니다.
// $exchangeapi->token = session_create_id();
session_start();
session_regenerate_id(); // 로그인할때마다 token 값을 바꿉니다.

// 로그인 세션 확인.
// $exchangeapi->checkLogout();

$c_index = setDefault(loadParam('c_index'), '');
$c_name = setDefault(loadParam('c_name'), '');
$c_call = setDefault(loadParam('c_call'), '');
$c_address1 = setDefault(loadParam('c_address1'), '');
$c_address2 = setDefault(loadParam('c_address2'), '');
$c_order = setDefault(loadParam('c_order'), '');
$c_ordernum = setDefault(loadParam('c_ordernum'), '');
$c_sendtext = setDefault(loadParam('c_sendtext'), '');


// --------------------------------------------------------------------------- //

$sql = " UPDATE `kkikda`.`js_test_order` 
			SET `complete`='Y', 'complete_manager' = '1'
			WHERE  `sms_index`='$c_index';";

$u_data = $tradeapi->query_list_object($sql);

$sql2 = " INSERT INTO `kkikda`.`js_test_order` (`call`, `order_item`, `order_num`, `address`, `order_manager`) 
			VALUES ('$c_call', '$c_order', $c_ordernum, '$c_address1', '1');
		";

$u2_data = $tradeapi->query_list_object($sql2);

sendSMS($c_call, $c_sendtext);


$tradeapi->success($u2_data);



?>