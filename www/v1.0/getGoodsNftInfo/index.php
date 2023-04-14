<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
$tradeapi->checkLogin();
$userno = $tradeapi->get_login_userno();

// validate parameters
$symbol = checkSymbol(strtoupper(checkEmpty($_REQUEST['symbol'], 'symbol')));
$goods_grade = checkEmpty($_REQUEST['goods_grade'], 'goods_grade');

// 상품(NFT) 정보 조회
$goods_info = (array) $tradeapi->query_fetch_object("SELECT * FROM js_auction_goods WHERE idx='{$tradeapi->escape($symbol)}' AND goods_grade='{$tradeapi->escape($goods_grade)}' ");

if(!$goods_info || !$goods_info['idx']){
    $tradeapi->error('500', __('상품 정보를 찾지 못했습니다.'));
}
$meta_data = $tradeapi->db_get_list('js_auction_goods_meta', array('goods_idx'=>$goods_info['idx']));
foreach($meta_data as $row) {
    $goods_info[$row->meta_key] = $row->meta_val;
}

// 상품정보
//$goods_info['title'];                       // 상품이름
//$goods_info['goods_grade'];                 // 상품등급
//$goods_info['main_pic'];                    // 상품이미지
//$goods_info['meta_division'];               // 구분
//$goods_info['meta_type'];                   // 타입
//$goods_info['meta_produce'];                // 생산
//$goods_info['meta_certification_mark'];     // 인증
//$goods_info['content'];                     // 차 소개


$_nft =  $tradeapi->query_list_object("select idx, nft_blockchain, nft_id, nft_tokenuri, nft_txnid from js_auction_goods where pack_info='{$tradeapi->escape($symbol)}' and goods_grade='{$tradeapi->escape($goods_grade)}' and owner_userno='{$tradeapi->escape($userno)}'");

$nft_info = array();
foreach ($_nft as $k => $v) {
    $nft_info[] = (array) $_nft[$k];
}

$r = array('good_info'=> $goods_info, 'nft_info'=>$nft_info);
$tradeapi->success($r);