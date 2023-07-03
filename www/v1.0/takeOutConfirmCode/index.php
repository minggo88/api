<?php
/**
 * 인증 코드 보내기
 * 전화번호나 이메일의 실제 사용자인지 확인하기 위해 인증 코드를 발송합니다.
 * @todo firebase sms, email 인증을 서버 코드로 적용하기. https://firebase.google.com/docs/admin/setup?authuser=3, https://firebase-php.readthedocs.io/en/5.x/
 */
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
// $tradeapi->checkLogin();
$userno = $tradeapi->get_login_userno();

// validate parameters
$media = checkMedia(strtolower(checkEmpty(loadParam('media'), 'media'))); // 기기종류. mobile: 핸드폰, email: 이메일
$mobile_number = setDefault(loadParam('mobile_number'), ''); // 핸드폰 번호 , 이메일 주소. 예)id1@domain.com,id2@domain.com,...
$mobile_country_code = setDefault(loadParam('mobile_country_code'), ''); // 핸드폰 번호 , 이메일 주소. 예)id1@domain.com,id2@domain.com,...
$email_address = setDefault(loadParam('email_address'), ''); // 핸드폰 번호 , 이메일 주소. 예)id1@domain.com,id2@domain.com,...

// value 값 확인.
if($media=='mobile') {
    $mobile_number = checkMobileNumber(checkEmpty($mobile_number, 'mobile_number'));
    if(strpos($mobile_number, '+')!==0) {
        $mobile_number = '+'.$mobile_number;
    }
    $mobile_country_code = checkCountryCode(checkEmpty($mobile_country_code,'mobile_country_code'));
}
if($media=='email') {
    $email_address = checkEmail(checkEmpty($email_address,'email_address'));
}

// --------------------------------------------------------------------------- //

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('master');


// 인증 코드 생성.
if($media=='mobile') {
    $tmpnum = mt_rand(111111, 999999);
}
if($media=='email') {
    $tmpnum = mt_rand(111111, 999999);
}

// 가입여부 확인
// if($media=='mobile') {
// 	$t = str_replace('+82010', '8210', $mobile_number);
// 	$t = str_replace('+8210', '8210', $t);
// 	$joind = $tradeapi->query_one("select userno from js_member where mobile='{$tradeapi->escape($t)}'");
// 	if($joind) {
// 		$tradeapi->error('035', __('이미 가입하셨습니다.').' '.__('로그인 해주세요.'));
// 	}
// }
// if($media=='email') {
// 	$joind = $tradeapi->query_one("select userno from js_member where email='{$tradeapi->escape($email_address)}'");
// 	if($joind) {
// 		$tradeapi->error('035', __('이미 가입하셨습니다.').' '.__('로그인 해주세요.'));
// 	}
// }

// send
if($media=='mobile') {
	if($userno) {
//		$sql = "update js_member set confirm_number='$tmpnum', mobile='', bool_confirm_mobile=0, confirm_mobile_number='{$tradeapi->escape($mobile_number)}', mobile_country_code='{$tradeapi->escape($mobile_country_code)}' where userno='".$tradeapi->escape($userno)."' ";
		$sql = "update js_member set confirm_number='$tmpnum', confirm_mobile_number='{$tradeapi->escape($mobile_number)}', mobile_country_code='{$tradeapi->escape($mobile_country_code)}' where userno='".$tradeapi->escape($userno)."' ";
		if(!$tradeapi->query($sql)) {
			$tradeapi->error('036', __('Failed to send confirm code.'));
		}
	} else {
		$_SESSION['confirm_number'] = $tmpnum;
		$_SESSION['confirm_mobile_number'] = $mobile_number;
		$_SESSION['mobile_country_code'] = $mobile_country_code;
	}
	$config_basic = $tradeapi->get_config('js_config_basic');
	$tran_msg  = '['.$config_basic->shop_name.'] ' . str_replace('{tmpnum}', $tmpnum, __('Your verification code is: {tmpnum}') );
	// if(! $tradeapi->send_sms($mobile_country_code, $mobile_number, $tran_msg)) {
	if(! $tradeapi->send_sms($mobile_number, $tran_msg)) {
		$tradeapi->error('037', __('Failed to send confirm code.'));
	}
}
if($media=='email') {
	$_SESSION['confirm_number'] = $tmpnum;
	$_SESSION['confirm_email_address'] = $email_address;
	// var_dump($email_address, __('인증번호를 입력해주세요.'), __('Your verification code is: {tmpnum}', array('{tmpnum}'=>$tmpnum))); exit;
	$r = $tradeapi->send_email($email_address, __('반출신청 예정자가 있습니다.'), __('Your verification code is: {tmpnum}', array('{tmpnum}'=>$tmpnum)));
    // 작업 필요합니다.
	if(!$r) {
        $tradeapi->error('200', __('Failed to send confirm code.').' '.$tradeapi->send_email_error_msg);
    }
}

// 세션에 저장. - send 완료후 저장.
$_SESSION['sms_tmpnum'] = $tmpnum;
$_SESSION['sms_tmpnum_time'] = time();

// response
$tradeapi->success(true);
