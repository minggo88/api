<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
$tradeapi->checkLogin();

// validate parameters
$address = checkEmpty(loadParam('address'),'address'); // 이메일주소 또는 전화번호
$address_type = checkEmpty(loadParam('address_type'),'address_type'); // email 또는 mobile
if($address_type!='email' && $address_type!='mobile') {
    $tradeapi->error('100', __('Please enter your email.'));
}

// check member
if($address_type=='email') {
    $member_info = $tradeapi->query_fetch_object("SELECT userno, userid, email, mobile, name FROM js_member WHERE userid = '{$tradeapi->escape($address)}' ");
    // $address = $member_info->email;
}

if(!$member_info) {
    $tradeapi->error('110', __('There is no account information.'));
}

//임시패스워드 발급하기
$tmp_pw = $tradeapi->genRandomString(16);
// 임시 번호 생성해 확인하기. - 지금은 저장.
$tradeapi->set_member_meta($member_info->userno, 'tmp_pinnumber', $tmp_pw);
$tradeapi->set_member_meta($member_info->userno, 'tmp_pinnumber_time', time());

// 사이트 정보 추출
$site_code = $tradeapi->get_site_code();
$config_basic = $tradeapi->query_fetch_object("SELECT * FROM js_config_basic WHERE code='{$tradeapi->escape($site_code)}'");

$domain = str_replace('api.', '', $_SERVER['HTTP_HOST']); // 서비스 도메인과 맞추기위해 api. 을 삭제합니다.
// $domain = substr_count($domain, '.')<2 ? 'www.'.$domain : $domain; // 라이브 서비스에서는 www.cexsctock.com 이나 www.smcc.io 같은 도메인을 사용하기 때문임.
$domain = str_replace('www.', '', $domain); // 라이브 서비스에서는 www.cexsctock.com 이나 www.smcc.io 같은 도메인을 사용하기 때문임.
$domain = (__API_RUNMODE__=='loc' ? 'http://' : 'https://').$domain;

if($address_type=='email') {
    $member_info->dear_name_str = $member_info->name ? __("Dear {member_name},", array('{member_name}' => $member_info->name)) : '';
    ob_clean();
    ob_start();
    include __DIR__.'/tpl_pinnumber_email.php';
    $html = ob_get_contents();
    ob_end_clean();
    $title = $member_info->name ? __('{name}의 핀번호 변경 안내입니다.', array('{name}'=>$member_info->name)) : '핀번호 변경 안내입니다.';
    $r = $tradeapi->send_email($address, $title, $html);
    // var_dump($html, $title); exit;
    if(!$r) {
        $tradeapi->error('200', __('Failed to send email.').' '.$tradeapi->send_email_error_msg);
    }
}

// response
$tradeapi->success($r);
