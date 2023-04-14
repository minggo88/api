<?php
include dirname(__file__) . "/../../lib/TradeApi.php";
// $tradeapi->set_logging(true);
// if(__API_RUNMODE__=='live'||__API_RUNMODE__=='loc') {
// 	$tradeapi->set_log_dir($tradeapi->log_dir.'/'.basename(__dir__).'/');
// } else {
// 	// $tradeapi->set_log_dir(__dir__.'/');
// }
// $tradeapi->set_log_name('');
// $tradeapi->write_log("REQUEST: " . json_encode($_REQUEST));

/**
 * 가입 확인
 */

// 로그인 세션 확인.
// $tradeapi->checkLogin();
// $userno = $tradeapi->get_login_userno();

// validate parameters
$media = checkMedia(strtolower(checkEmpty(loadParam('media'), 'media'))); // 기기종류. mobile: 핸드폰, email: 이메일
$values = setDefault(loadParam('ids'), ''); // 이메일 주소. 예)id1@domain.com,id2@domain.com,...

// $deposit_info = '농협 2012/02/11 841104-51-015988 10,000원(홍길동)입금.잔액2,249,718원'; // test value

// --------------------------------------------------------------------------- //

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('slave');

$values = explode(',', $values);
$origin_values = $values;
if($media=='mobile') {
    for($i=0; $i<count($values); $i++) {
        $values[$i] = preg_replace('/[^0-9]/','',$values[$i]); // +82 처럼 국가번호구분자인 + 값을 모두 지운다
        $values[$i] = $tradeapi->reset_phone_number($values[$i]);
    }
}

$r = array();
$joined = $tradeapi->check_join($media, $values);
foreach($joined as $row) {
    if($row->values && ! $r[$row->values]) {
        $i = array_search($row->values, $values);
        $r[$origin_values[$i]] = $row->status;
    }
}
$in_progress = $tradeapi->check_waiting($media, $values);
foreach($in_progress as $row) {
    if($row->values && ! $r[$row->values]) {
        $r[$row->values] = $row->status;
    }
}
$t = array();
// __('in progress');, __('joined');
foreach($r as $values => $status) {
    $t[] = array('id'=>$values,  'status'=>__($status));
}

// response
$tradeapi->success($t);
