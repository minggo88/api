<?php
include dirname(__file__) . "/../../lib/ExchangeApi.php";

// 로그인 세션 확인.
// $exchangeapi->checkLogin();
// $userno = $exchangeapi->get_login_userno();

// validate parameters
$author = checkEmpty(loadParam('author')); // 
$contents = checkEmpty(loadParam('contents')); // 
$subject = setDefault(loadParam('subject'), iconv_substr($contents, 0, 50, 'UTF-8')); // 
$userid = setDefault($exchangeapi->get_login_userid(),''); // 
$comname = setDefault(loadParam('comname'),''); // 
$position = setDefault(loadParam('position'),''); // 
$email = setDefault(loadParam('email'),''); // 
$phone = setDefault(loadParam('phone'),''); // 
$mobile = setDefault(loadParam('mobile'),''); // 
$ipaddr = $_SERVER['REMOTE_ADDR']; // 

// --------------------------------------------------------------------------- //

// 마스터 디비 사용하도록 설정.
$exchangeapi->set_db_link('master');

// get my member information
$r = $exchangeapi->query("INSERT INTO js_request SET userid='{$exchangeapi->escape($userid)}', author='{$exchangeapi->escape($author)}', comname='{$exchangeapi->escape($comname)}', position='{$exchangeapi->escape($position)}', subject='{$exchangeapi->escape($subject)}', contents='{$exchangeapi->escape($contents)}', email='{$exchangeapi->escape($email)}', phone='{$exchangeapi->escape($phone)}', mobile='{$exchangeapi->escape($mobile)}', ipaddr='{$exchangeapi->escape($ipaddr)}', hit='0', regdate=UNIX_TIMESTAMP() ");

// response
if($r) {
    $exchangeapi->success( array('idx'=>$exchangeapi->_recently_query['last_insert_id']) );
} else {
    $exchangeapi->error( '000', __('A system error has occurred.') );
}
