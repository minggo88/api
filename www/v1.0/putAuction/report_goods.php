<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

$tradeapi->set_logging(true);
$tradeapi->set_log_dir(__dir__.'/../../log/'.basename(__dir__).'/');
$tradeapi->set_log_name(basename(__file__));
$tradeapi->write_log("REQUEST: " . json_encode($_REQUEST));

/**
 * 경매 상품 생성/수정
 */
// 로그인 세션 확인.
$tradeapi->checkLogin();

$userid = $tradeapi->get_login_userid();
$userno = $tradeapi->get_login_userno();

$goods_idx      = setDefault($_REQUEST['goods_idx'], '');     // 상품번호
$report_type      = setDefault($_REQUEST['report_type'], ''); // 신고유형(C:저작권 관련, S:성적인 콘텐츠, R: 인종차별, A: 광고성, E: 기타)
$report_desc      = setDefault($_REQUEST['report_desc'], ''); // 신고내용

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('master');

// 상품(NFT) 정보 조회
$goods_info = $tradeapi->query_fetch_object("SELECT * FROM js_auction_goods WHERE idx='{$tradeapi->escape($goods_idx)}'");
if(!$goods_info) {
    $tradeapi->error('100', __('There is no product.'));
}

// 중복신고 확인
$report_info = $tradeapi->query_fetch_object("SELECT * FROM js_auction_goods_report WHERE goods_idx='{$tradeapi->escape($goods_idx)}' AND report_userno='{$tradeapi->escape($userno)}'");
if($report_info) {
    $tradeapi->error('101', __('It is already reported.'));
}

// 신고내용 저장
$report_idx = $tradeapi->gen_id();
$tradeapi->query("INSERT INTO js_auction_goods_report SET report_idx='{$tradeapi->escape($report_idx)}', goods_idx='{$tradeapi->escape($goods_idx)}', report_type='{$tradeapi->escape($report_type)}', report_desc='{$tradeapi->escape($report_desc)}', reg_date=NOW(), report_userno='{$tradeapi->escape($userno)}' ");

// 관리자에게 푸시 보내기??

$tradeapi->success(array('report_idx'=>$report_idx));
