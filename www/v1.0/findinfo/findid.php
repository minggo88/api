<?php
include dirname(__file__) . "/../../lib/TradeApi.php"; // findid 

// 로그인 세션 확인.
$tradeapi->checkLogout();
// $userno = $tradeapi->get_login_userno();

// validate parameters
$name = checkEmpty(loadParam('name'),'name'); // 이름 
$address = checkEmpty(loadParam('address'),'address'); // 이메일주소 또는 전화번호
$address_type = checkEmpty(loadParam('address_type'),'address_type'); // email 또는 mobile
if($address_type!='email' && $address_type!='mobile') {
    $tradeapi->error('100', __('Please enter your email or phone number.'));
}

// 사이트 정보 추출
$site_code = $tradeapi->get_site_code();
$config_basic = $tradeapi->query_fetch_object("SELECT * FROM js_config_basic WHERE code='{$tradeapi->escape($site_code)}'");

// check member
if($address_type=='email') {
	$sql = "SELECT userno, userid, email, mobile, name, regdate regtime FROM js_member WHERE name='{$tradeapi->escape($name)}' AND email = '{$tradeapi->escape($address)}' ";
    $member_info = $tradeapi->query_fetch_object($sql);
    // $address = $member_info->email;
}
if($address_type=='mobile') {
    // $address = preg_replace('/^0/', '', $address);
    $address = $tradeapi->reset_phone_number($address);
	$sql = "SELECT userno, userid, email, mobile, name, regdate regtime FROM js_member WHERE name='{$tradeapi->escape($name)}' AND mobile = '{$tradeapi->escape($address)}' ";
    $member_info = $tradeapi->query_fetch_object($sql);
    // $address = $member_info->mobile;
}
if(!$member_info) {
    $tradeapi->error('110', __('There is no account information.'));
}

// response
$tradeapi->success(array('userid'=>$member_info->userid, 'regtime'=>$member_info->regtime, 'regdate'=>date('Y-m-d H:i:s', $member_info->regtime)));
