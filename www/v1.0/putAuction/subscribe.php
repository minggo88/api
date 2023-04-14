<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

$tradeapi->set_logging(true);
$tradeapi->set_log_dir(__dir__.'/../../log/'.basename(__dir__).'/');
$tradeapi->set_log_name(basename(__file__));
$tradeapi->write_log("REQUEST: " . json_encode($_REQUEST));

/**
 * 경매 생성/수정
 */
// 로그인 세션 확인.
$tradeapi->checkLogin();

$userid = $tradeapi->get_login_userid();
$userno = $tradeapi->get_login_userno();

$target_idx    = setDefault($_REQUEST['target_idx'], '');                 // 구독 대상 회원번호
// if($userno==$target_idx) {
//     $tradeapi->error('101', __('스스로에게 할 수 없습니다.'));
// }

$subscribe_type    = setDefault($_REQUEST['subscribe_type'], 'like');     // 구독종류. like: "좋아요" 처리. subscribe:"구독"처리, notification:"알람"처리
if($subscribe_type!='like' && $subscribe_type!='subscribe' && $subscribe_type!='notification') {
    $tradeapi->error('102', __('구독종류를 올바르게 입력해주세요.'));
}

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('master');

// 구독 추가여부
$_added = '';

// DB 등록 여부
$subscribe_info = $tradeapi->query_fetch_object("SELECT * FROM js_auction_subscribe WHERE subscriber_userno='{$tradeapi->escape($userno)}' AND target_idx='{$tradeapi->escape($target_idx)}'");
if($subscribe_info) { // 이미 row가 있으면 UPDATE
    switch($subscribe_type) {
        case 'like': $_added=$subscribe_info->like=='Y'?'N':'Y'; $sql_update = " `like`='{$_added}' "; break;
        case 'subscribe': $_added=$subscribe_info->subscribe=='Y'?'N':'Y'; $sql_update = " `subscribe`='{$_added}' "; break;
        case 'notification': $_added=$subscribe_info->notification=='Y'?'N':'Y'; $sql_update = " `notification`='{$_added}' "; break;
    }
	$r = $tradeapi->query("UPDATE js_auction_subscribe SET mod_date=NOW(), {$sql_update} WHERE subscriber_userno='{$tradeapi->escape($userno)}' AND target_idx='{$tradeapi->escape($target_idx)}'");
} else { // 없으면 처음 insert
    $_added='Y';
    switch($subscribe_type) {
        case 'like': $sql_update = " `like`='{$_added}' "; break;
        case 'subscribe': $sql_update = " `subscribe`='{$_added}' "; break;
        case 'notification': $sql_update = " `notification`='{$_added}' "; break;
    }
	$r = $tradeapi->query("INSERT INTO js_auction_subscribe SET subscriber_userno='{$tradeapi->escape($userno)}', target_idx='{$tradeapi->escape($target_idx)}', reg_date=NOW(), {$sql_update} ");
}

// goods테이블에 like 수 갱신
if($subscribe_type=='like') {
    $tradeapi->query("UPDATE js_auction_goods g SET cnt_like=(SELECT COUNT(*) FROM `js_auction_subscribe` WHERE target_idx = g.idx AND `like`='Y') WHERE g.idx='{$tradeapi->escape($target_idx)}' ");
}

// response
if($r){
	$tradeapi->success(array('added'=>$_added));
}else{
	$tradeapi->error('005', __('A system error has occurred.'));
}
