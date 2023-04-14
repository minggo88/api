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
// $tradeapi->checkLogin();
// $userid = $tradeapi->get_login_userid();
$userno = $tradeapi->get_login_userno();

$goods_idx      = setDefault($_REQUEST['goods_idx'], '');                   //js_auction_goods.idx

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('master');

// 1시간에 한번만 카운트 증가
// if(time() - $_SESSION['view_time_'.$goods_idx] > 60*6081) {

    // 조회수 증가
    $tradeapi->query("UPDATE js_auction_goods SET cnt_view=cnt_view+1 WHERE idx='{$tradeapi->escape($goods_idx)}'");

    // 세션에 저장
    // $_SESSION['view_time_'.$goods_idx] = time();

// }


$tradeapi->success(array('goods_idx'=>$goods_idx));
