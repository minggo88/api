<?php
/**
 * 인증 코드 보내기 및 PMS 이메일 전송
 * 1. 전화번호나 이메일의 실제 사용자인지 확인하기 위해 인증 코드를 발송합니다.
 * 2. PMS 보고서를 이메일로 전송합니다.
 */
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
// $tradeapi->checkLogin();
$userno = $tradeapi->get_login_userno();

// validate parameters
$media = checkMedia(strtolower(checkEmpty(loadParam('media'), 'media'))); // 기기종류. mobile: 핸드폰, email: 이메일
$mobile_number = setDefault(loadParam('mobile_number'), ''); // 핸드폰 번호
$mobile_country_code = setDefault(loadParam('mobile_country_code'), ''); // 국가코드
$email_address = setDefault(loadParam('email_address'), ''); // 이메일 주소
$message_text = setDefault(loadParam('message_text'), ''); // 메시지 내용
$message_type = setDefault(loadParam('message_type'), 'auth'); // 메시지 타입: auth(인증코드), pms(PMS보고서), takeout(반출신청)

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

// 메시지 타입에 따라 처리 분기
if($media=='mobile') {
    // SMS 전송 (인증코드)
    $tmpnum = mt_rand(111111, 999999);
    
    if($userno) {
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
    
    if(! $tradeapi->send_sms($mobile_number, $tran_msg)) {
        $tradeapi->error('037', __('Failed to send confirm code.'));
    }
    
    // 세션에 저장
    $_SESSION['sms_tmpnum'] = $tmpnum;
    $_SESSION['sms_tmpnum_time'] = time();
}

if($media=='email') {
    // 이메일 타입에 따라 분기 처리
    switch($message_type) {
        case 'pms':
            // PMS 보고서 전송
            $subject = 'PMS 보고서';
            $body = $message_text;
            
            // PMS 보고서는 인증번호 필요 없음
            $r = $tradeapi->send_email($email_address, $subject, $body);
            
            if(!$r) {
                $tradeapi->error('200', __('PMS 보고서 전송에 실패했습니다.').' '.$tradeapi->send_email_error_msg);
            }
            break;
            
        case 'takeout':
            // 반출 신청 이메일
            $tmpnum = mt_rand(111111, 999999);
            $subject = __('반출신청 예정자가 있습니다.');
            $body = __('신청자: '.$userno.', 내용 : '.$message_text.'');
            
            $_SESSION['confirm_number'] = $tmpnum;
            $_SESSION['confirm_email_address'] = $email_address;
            $_SESSION['sms_tmpnum'] = $tmpnum;
            $_SESSION['sms_tmpnum_time'] = time();
            
            $r = $tradeapi->send_email($email_address, $subject, $body);
            
            if(!$r) {
                $tradeapi->error('200', __('반출 신청 이메일 전송에 실패했습니다.').' '.$tradeapi->send_email_error_msg);
            }
            break;
            
        case 'auth':
        default:
            // 인증 코드 전송 (기존 방식)
            $tmpnum = mt_rand(111111, 999999);
            $subject = __('인증번호를 입력해주세요.');
            $body = __('Your verification code is: {tmpnum}', array('{tmpnum}'=>$tmpnum));
            
            $_SESSION['confirm_number'] = $tmpnum;
            $_SESSION['confirm_email_address'] = $email_address;
            $_SESSION['sms_tmpnum'] = $tmpnum;
            $_SESSION['sms_tmpnum_time'] = time();
            
            $r = $tradeapi->send_email($email_address, $subject, $body);
            
            if(!$r) {
                $tradeapi->error('200', __('인증 코드 전송에 실패했습니다.').' '.$tradeapi->send_email_error_msg);
            }
            break;
    }
}

// response
$tradeapi->success(true);