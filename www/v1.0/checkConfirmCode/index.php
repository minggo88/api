<?php
include dirname(__file__) . "/../../lib/ExchangeApi.php";

$exchangeapi->set_logging(true);
$exchangeapi->set_log_dir(__dir__.'/../../log/'.basename(__dir__).'/');
$exchangeapi->set_log_name('');
$exchangeapi->write_log("REQUEST: " . json_encode($_REQUEST));

/**
 * 인증코드 확인
 */

// 로그인 세션 확인.
// $exchangeapi->checkLogin();
$userno = $exchangeapi->get_login_userno();

// validate parameters
$media = checkMedia(strtolower(checkEmpty(loadParam('media'), 'media'))); // 기기종류. mobile: 핸드폰, email: 이메일
$confirm_number = checkEmpty(setDefault(loadParam('confirm_number'), '')); // 핸드폰 번호 , 이메일 주소. 예)id1@domain.com,id2@domain.com,...
$mobile_number = setDefault(loadParam('mobile_number'), ''); // 핸드폰 번호 , 이메일 주소. 예)id1@domain.com,id2@domain.com,...
$email_address = setDefault(loadParam('email_address'), ''); // 핸드폰 번호 , 이메일 주소. 예)id1@domain.com,id2@domain.com,...

// value 값 확인.
if($media=='mobile') {
    $mobile_number = checkMobileNumber(checkEmpty($mobile_number, 'mobile_number'));
    if(strpos($mobile_number, '+')!==0) {
        $mobile_number = '+'.$mobile_number;
    }
}
if($media=='email') {
    $email_address = checkEmail(checkEmpty($email_address,'email_address'));
}

// --------------------------------------------------------------------------- //

// 마스터 디비 사용하도록 설정.
$exchangeapi->set_db_link('master');

// check & reset
$confirmed = false;
if($media=='mobile') {
	if($userno) {
        $_member_info = $exchangeapi->get_user_info($userno);
        if( $_member_info->confirm_number == $confirm_number ) {
            $confirmed = true;
//            $sql = "update js_member set bool_confirm_mobile='1', confirm_mobile_number='', mobile='".$exchangeapi->escape($_member_info->confirm_mobile_number)."', confirm_number='', bool_realname='1' where userno='".$exchangeapi->escape($userno)."' ";
            $sql = "update js_member set bool_confirm_mobile='1', confirm_mobile_number='', confirm_number='', bool_realname='1' where userno='".$exchangeapi->escape($userno)."' ";
            if(!$exchangeapi->query($sql)) {
                $exchangeapi->error('036', __('Failed to validate confirm code.'));
            }
        }
	} else {
        if( $_SESSION['confirm_number'] == $confirm_number ) {
            $confirmed = true;
            $_SESSION['confirm_number'] = ''; unset($_SESSION['confirm_number']);
            $_SESSION['confirm_mobile_number'] = ''; unset($_SESSION['confirm_mobile_number']);
            $_SESSION['mobile_country_code'] = ''; unset($_SESSION['mobile_country_code']);
        }
    }
}
if($media=='email') {
    if( $_SESSION['confirm_number'] == $confirm_number ) {
        $confirmed = true;
        $_SESSION['confirm_number'] = ''; unset($_SESSION['confirm_number']);
        $_SESSION['confirm_mobile_number'] = ''; unset($_SESSION['confirm_mobile_number']);
		if($userno) {
            $sql = "UPDATE js_member SET bool_confirm_email='1' WHERE userno='".$exchangeapi->escape($userno)."' ";
            if(!$exchangeapi->query($sql)) {
                $exchangeapi->error('038', __('Failed to validate confirm code.'));
            }
		}
    }
}

// response
if( $confirmed ) {
    $exchangeapi->success(true);        
} else {
    $exchangeapi->error('037', __('Failed to validate confirm code.').' ' .__('Please enter a valid verification code.'));
}


