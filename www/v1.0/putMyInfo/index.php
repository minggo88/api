<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
$tradeapi->checkLogin();
$userno = $tradeapi->get_login_userno();

$_REQUEST['userno'] = $userno;


// 이미지 s3 정식폴더로 이동
$s3_check_param = array('image_identify_url', 'image_mix_url', 'image_bank_url');
foreach($s3_check_param as $param) {
    $file = $_REQUEST[$param];
    if($file && strpos($file, '.s3.')!==false && strpos($file, '/tmp/')!==false) {
        $_REQUEST[$param] = $tradeapi->move_tmpfile_to_s3($file);
    }
}

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('master');

// get my member information
$r = $tradeapi->save_member_info($_REQUEST);

// response
$tradeapi->success($r);
