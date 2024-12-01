<?php
include dirname(__file__) . "/../../lib/ExchangeApi.php";

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
$exchangeapi->set_logging(true);
// $exchangeapi->set_log_dir(__dir__.'/../../log/'.basename(__dir__).'/');
// if(__API_RUNMODE__=='live'||__API_RUNMODE__=='loc') {
	$exchangeapi->set_log_dir($exchangeapi->log_dir.'/'.basename(__dir__).'/');
// } else {
	// $exchangeapi->set_log_dir(__dir__.'/');
// }
$exchangeapi->set_log_name('');
$exchangeapi->write_log("REQUEST: " . json_encode($_REQUEST));

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

// 마스터 디비 사용하도록 설정.
$exchangeapi->set_db_link('master');

$exchangeapi->transaction_start();// DB 트랜젝션 시작

// 가입
$sql = " UPDATE `kkikda`.`js_test_order` 
			SET `complete`='Y', 'complete_manager' = '1'
			WHERE  `sms_index`='$c_index';";

$exchangeapi->query($sql);

$member = $exchangeapi->get_member_info_by_userid($userid);

$sql2 = " INSERT INTO `kkikda`.`js_test_order` (`call`, `order_item`, `order_num`, `address`, `order_manager`) 
			VALUES ('$c_call', '$c_order', $c_ordernum, '$c_address1', '1');
		";

$exchangeapi->query($sql2);

$member = $exchangeapi->get_member_info_by_userid($userid);

$exchangeapi->transaction_end('commit');// DB 트랜젝션 끝

sendSMS($c_call, $c_sendtext);

// response
$exchangeapi->success(array('token'=>"success",'my_wallet_no'=>"1111",'userno'=>"2222"));




?>