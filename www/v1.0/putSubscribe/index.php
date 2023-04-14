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

$target_idx    = checkEmpty($_REQUEST['target_idx'], 'target_idx');                 // 구독 대상 회원번호
$target_type    = checkEmpty($_REQUEST['target_type'], 'target_type');                 // 구독 대상 종류.  trade: 거래소(종목), auction: 경매(상품), shop: 쇼핑몰(상품), member: 회원, blog: 블로그, ...
if($target_type=='member' && $userno==$target_idx) {
    $tradeapi->error('101', __('스스로에게 할 수 없습니다.'));
}

$subscribe_type    = setDefault($_REQUEST['subscribe_type'], 'like');     // 구독종류. like: "좋아요" 처리. subscribe:"구독"처리, notification:"알람"처리
if($subscribe_type!='like' && $subscribe_type!='subscribe' && $subscribe_type!='notification') {
    $tradeapi->error('102', __('구독종류를 올바르게 입력해주세요.'));
}

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('master');

// 구독 추가여부
$_added = '';

$where = "WHERE subscriber_userno='{$tradeapi->escape($userno)}' AND target_idx='{$tradeapi->escape($target_idx)}' AND target_type='{$tradeapi->escape($target_type)}'";

// DB 등록 여부
$subscribe_info = $tradeapi->query_fetch_object("SELECT * FROM js_subscribe {$where}");
if($subscribe_info) { // 이미 row가 있으면 UPDATE
    switch($subscribe_type) {
        case 'like': 
            $_added = $subscribe_info->like == 'Y'?'N':'Y'; 
            $_added_date = $subscribe_info->like == 'Y'?'NULL':"'".date('Y-m-d H:i:s')."'"; 
            $sql_update = " `like`='{$_added}', `like_date`={$_added_date} "; 
            break;
        case 'subscribe': 
            $_added=$subscribe_info->subscribe == 'Y'?'N':'Y'; 
            $_added_date = $subscribe_info->subscribe == 'Y'?'NULL':"'".date('Y-m-d H:i:s')."'"; 
            $sql_update = " `subscribe`='{$_added}', `subscribe_date`={$_added_date} "; 
            break;
        case 'notification': 
            $_added=$subscribe_info->notification == 'Y'?'N':'Y'; 
            $_added_date = $subscribe_info->notification == 'Y'?'NULL':"'".date('Y-m-d H:i:s')."'"; 
            $sql_update = " `notification`='{$_added}', `notification_date`={$_added_date} "; 
            break;
    }
	$r = $tradeapi->query("UPDATE js_subscribe SET mod_date=NOW(), {$sql_update} {$where}");
} else { // 없으면 처음 insert
    $_added='Y';
    switch($subscribe_type) {
        case 'like': $sql_update = " `like`='{$_added}', `like_date`=NOW() "; break;
        case 'subscribe': $sql_update = " `subscribe`='{$_added}', `subscribe_date`=NOW() "; break;
        case 'notification': $sql_update = " `notification`='{$_added}', `notification_date`=NOW() "; break;
    }
	$r = $tradeapi->query("INSERT INTO js_subscribe SET subscriber_userno='{$tradeapi->escape($userno)}', target_idx='{$tradeapi->escape($target_idx)}', target_type='{$tradeapi->escape($target_type)}', reg_date=NOW(), {$sql_update} ");
}

// auction goods테이블에 like 수 갱신
if($target_type=='auction' && $subscribe_type=='like') {
    $tradeapi->query("UPDATE js_auction_goods g SET cnt_like=(SELECT COUNT(*) FROM `js_subscribe` WHERE target_idx = g.idx AND `like`='Y') WHERE g.idx='{$tradeapi->escape($target_idx)}' ");
}

// response
if($r){
	$tradeapi->success(array('added'=>$_added));
}else{
	$tradeapi->error('005', __('A system error has occurred.'));
}
