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

$goods_idx      = setDefault($_REQUEST['goods_idx'], '');                   //js_auction_goods.idx

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('master');

// 상품(NFT) 정보 조회
$goods_info = $tradeapi->query_fetch_object("SELECT * FROM js_auction_goods WHERE idx='{$tradeapi->escape($goods_idx)}'");
if(!$goods_info) {
    $tradeapi->error('100', __('There is no product.'));
}
// 분할 된 상품은 전부 소유한 사람만 삭제 가능 - 일단 분할된 상품은 삭제 불가처리. 이거 풀리면 다른 회원의 보유량도 삭제되서 큰일납니다.
if($goods_info->nft_max_supply>1) {
    $tradeapi->error('103', __('Splited products cannot be deleted.'));
}
// 소유자인지 확인
if($goods_info->owner_userno != $userno) {
    $tradeapi->error('101', __('Only the owner of the product can be deleted.'));
}

// 경매 정보 조회
$auction_info = $tradeapi->query_fetch_object("SELECT * FROM js_auction_list WHERE goods_idx='{$tradeapi->escape($goods_idx)}' ORDER BY auction_idx DESC LIMIT 1");
// 경매 시작 안했으면 삭제 가능
if($auction_info && $auction_info->start_date<=date('Y-m-d H:i:s')) {
    // 입찰자가 있는지 확인 없으면 삭제가능
    $biders = $tradeapi->query_one("SELECT COUNT(*) FROM js_auction_apply WHERE auction_idx='{$tradeapi->escape($auction_info->auction_idx)}' ");
    if($biders>0) {
        $tradeapi->error('102', __('The auction info exist so can not be deleted.'));
    }
}
// 경매 진행중인지 확인.
// if($auction_info && ($auction_info->finish=='N' && date('Y-m-d H:i:s')<$auction_info->end_date )) {
//     $tradeapi->error('102', __('The auction info exist so can not be deleted.'));
// }
// 경매 정보가 있으면 삭제 불가
// if($auction_info) {
//     $tradeapi->error('102', __('The auction info exist so can not be deleted.'));
// }

// main_pic 삭제
if($goods_info->main_pic && $tradeapi->deletable_external_file('main_pic', $goods_info->main_pic))  {
    $tradeapi->delete_external_file($goods_info->main_pic);
}

// animation 삭제
if($goods_info->animation && $tradeapi->deletable_external_file('animation', $goods_info->main_pic))  {
    $tradeapi->delete_external_file($goods_info->animation);
}

// NFT 토큰URI 삭제
if($goods_info->nft_tokeuri)  {
    $tradeapi->delete_external_file($goods_info->nft_tokeuri);
}

$tradeapi->transaction_start();

// 상품 비활성화 처리
// $tradeapi->query("UPDATE js_auction_goods SET active='N' WHERE idx='{$tradeapi->escape($goods_idx)}' ");
// 상품 정보 삭제
$tradeapi->query("DELETE FROM js_auction_goods WHERE idx='{$tradeapi->escape($goods_idx)}' ");
$tradeapi->query("DELETE FROM js_auction_goods_meta WHERE goods_idx='{$tradeapi->escape($goods_idx)}' ");
$tradeapi->query("DELETE FROM js_auction_goods_company WHERE goods_idx='{$tradeapi->escape($goods_idx)}' ");
$tradeapi->query("DELETE FROM js_auction_inventory WHERE goods_idx='{$tradeapi->escape($goods_idx)}' ");
$tradeapi->query("DELETE FROM js_auction_apply_list WHERE auction_idx IN (SELECT auction_idx FROM js_auction_list WHERE goods_idx='{$tradeapi->escape($goods_idx)}') ");
$tradeapi->query("DELETE FROM js_auction_apply WHERE goods_idx='{$tradeapi->escape($goods_idx)}' ");
$tradeapi->query("DELETE FROM js_auction_txn WHERE goods_idx='{$tradeapi->escape($goods_idx)}' ");
$tradeapi->query("DELETE FROM js_auction_list WHERE goods_idx='{$tradeapi->escape($goods_idx)}' ");
// 지갑 정보 삭제
if($goods_info->nft_id && strtolower($goods_info->nft_id)!='working' && strtolower($goods_info->nft_id)!='pack' ) {
    // $wallet_nft_info = $tradeapi->query_fetch_object("SELECT userno, amount FROM js_exchange_wallet_nft WHERE tokenid='{$tradeapi->escape($goods_info->nft_id)}' AND symbol='{$tradeapi->escape($goods_info->nft_symbol)}' ");
    $tradeapi->query("UPDATE js_exchange_wallet SET confirmed=confirmed-1 WHERE userno='{$tradeapi->escape($goods_info->owner_userno)}' AND symbol='{$tradeapi->escape($goods_info->nft_symbol)}' ");
    $tradeapi->query("DELETE FROM js_exchange_wallet_nft WHERE tokenid='{$tradeapi->escape($goods_info->nft_id)}' AND symbol='{$tradeapi->escape($goods_info->nft_symbol)}' AND userno='{$tradeapi->escape($goods_info->owner_userno)}' ");
    $tradeapi->query("DELETE FROM js_exchange_wallet_txn WHERE nft_id='{$tradeapi->escape($goods_info->nft_id)}' AND symbol='{$tradeapi->escape($goods_info->nft_symbol)}' AND userno='{$tradeapi->escape($goods_info->owner_userno)}' ");
}
// 이벤트 등록제거
$tradeapi->query("DELETE FROM js_event_goods WHERE c_code='{$tradeapi->escape($goods_idx)}' ");

$tradeapi->transaction_end('commit');

$tradeapi->success(array('goods_idx'=>$goods_idx));
