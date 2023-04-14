<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

$tradeapi->set_logging(true);
$tradeapi->set_log_dir(__dir__.'/../../log/'.basename(__dir__).'/');
$tradeapi->set_log_name(basename(__file__));
$tradeapi->write_log("REQUEST: " . json_encode($_REQUEST));

/**
 * 경매 입찰
 * 경매 상품 입찰에 참여합니다.
 */
// 로그인 세션 확인.
$tradeapi->checkLogin();

$userid = $tradeapi->get_login_userid();
$userno = $tradeapi->get_login_userno();

$auction_idx= checkEmpty($_REQUEST['auction_idx'], 'auction_idx');  //auction idx
$bid_price      = checkNumber(checkEmpty($_REQUEST['price'], 'price'));          //입찰가격 - 증액분을 받아 계산했었는데... 동시에 들어오면 입찰시 보여진 금액과 차이가 나기때문에 클라에서 입찰금액을 받아 처리하는거으로 변경.
$bid_volume      = checkNumber(setDefault($_REQUEST['volume'], '1'));          //입찰수량
$bid_amount      = checkNumber(setDefault($_REQUEST['amount'], $bid_price*$bid_volume));          //입찰금액

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('master');

//경매 정보 확인
// $query = "SELECT g.idx FROM js_auction_list as a inner join js_auction_goods as g on a.goods_idx=g.idx
//     WHERE a.auction_idx='{$tradeapi->escape($auction_idx)}' and now()>=a.start_date and now()<=a.end_date";
// $get_auction_goods = $tradeapi->query_one($query);
$query = "SELECT auction_idx, goods_idx, start_date, end_date, price_symbol, sell_volume, creator_userno FROM js_auction_list WHERE auction_idx='{$tradeapi->escape($auction_idx)}' ";
$auction_info = $tradeapi->query_fetch_object($query);
if(!$auction_info){
    $tradeapi->error('105', __('No auction now.'));
}
if($auction_info->start_date && $auction_info->start_date>date('Y-m-d H:i:s')) {
	$tradeapi->error('106', __('경매가 시작되지 않았습니다.').' '.__('시작날짜를 확인해주세요.'));
}
if($auction_info->end_date && $auction_info->end_date<date('Y-m-d H:i:s')) {
	$tradeapi->error('107', __('경매가 종료되었습니다.').' '.__('다른 경매상품에 참여해주세요.'));
}
if($auction_info->finish && $auction_info->finish=='Y') {
	$tradeapi->error('108', __('경매가 종료되었습니다.').' '.__('다른 경매상품에 참여해주세요.'));
}
// 남은 수량 및 주문 수량 확인
$sold_volume = $tradeapi->query_one("SELECT IFNULL(SUM(auction_volume),0) FROM js_auction_apply WHERE auction_idx='{$tradeapi->escape($auction_idx)}' AND status='S'");
$sell_volume_remain = $auction_info->sell_volume - $sold_volume;
if($sell_volume_remain<$bid_volume) {
	$tradeapi->error('108', __('Please enter the order quantity below the remain quantity.'));
}

$symbol = $auction_info->price_symbol; // 입찰 화폐
$currency_info = $tradeapi->query_fetch_object("SELECT base_coin, auction_manager_userno, display_decimals FROM js_exchange_currency ec LEFT JOIN js_auction_currency ac ON ec.symbol=ac.symbol WHERE ec.symbol='{$tradeapi->escape($symbol)}' ");

$auction_fee = $tradeapi->query_fetch_object("SELECT fee_buy, fee_sell FROM js_config_auction WHERE code='{$tradeapi->escape($tradeapi->get_site_code())}'");
// 판매 수수료
// $sell_fee = $tradeapi->cal_percent_fee($auction_fee->fee_sell, $bid_amount, $currency_info->display_decimals);
// 입찰 수수료
$bid_fee = $tradeapi->cal_percent_fee($auction_fee->fee_buy, $bid_amount, $currency_info->display_decimals);
// var_dump($auction_fee->fee_buy, $bid_amount, $currency_info->display_decimals, '$bid_fee:'.$bid_fee);// exit;

//자신것은 입찰 못하게 막는다
// $query = "SELECT userid FROM js_auction_inventory WHERE userno='{$userno}' and goods_idx='{$tradeapi->escape($auction_info->goods_idx)}'";
// $chk_owner = $tradeapi->query_one($query);
// if($chk_owner){
//     $tradeapi->error('109', __('You can\'t bid your goods.'));
// }
if($auction_info->creator_userno == $userno){
    $tradeapi->error('109-1', __('You can\'t apply your goods.'));
}

//제품 최대 금액 확인
$query = "SELECT max(auction_price) as max_price FROM js_auction_apply WHERE auction_idx='{$tradeapi->escape($auction_idx)}' AND `status`='P' ";
$max_price = $tradeapi->query_one($query);
if($max_price >= $bid_price){
	$tradeapi->error('110', __('The bid price is less than or equal to the maximum price.'));
}

//사용자의 이전 입찰 금액, 입찰 수량 확인
$query = "SELECT auction_price as user_price, auction_amount as user_amount, auction_fee as user_fee FROM js_auction_apply WHERE auction_idx='{$tradeapi->escape($auction_info->auction_idx)}' AND goods_idx='{$tradeapi->escape($auction_info->goods_idx)}' AND userno='{$tradeapi->escape($userno)}' AND `status`='P' ";
$user_bid = $tradeapi->query_fetch_object($query);
if(!$user_bid) {
	$user_price = 0;
	$user_amount = 0;
	$user_fee = 0;
} else {
	$user_price = $user_bid->user_price;
	$user_amount = $user_bid->user_amount;
	$user_fee = $user_bid->user_fee;
}
$add_price = $bid_price - $user_price; //  추가가격 = 입찰가격 - 이전회원입찰가격;
$add_amount = $bid_amount - $user_amount; //  추가금액 = 입찰금액 - 이전회원입찰금액;
$add_fee = $bid_fee - $user_fee; //  추가수수료 = 입찰수수료 - 이전회원입찰수수료;
// var_dump($add_price, $add_amount, $add_fee, '$add_fee:'.$add_fee); //exit;

// 지갑 잔액 확인.
$user_wallet = $tradeapi->query_fetch_object("SELECT userno, symbol, confirmed, locked, autolocked, account, address FROM js_exchange_wallet WHERE userno='{$tradeapi->escape($userno)}' AND symbol='{$tradeapi->escape($symbol)}' ");
if($add_amount >= 0) {
	if($user_wallet->confirmed < $add_amount + $add_fee) {
		$tradeapi->error('111', __('There is not enough balance.'));
	}
}

// 관리자 지갑 확인
$manager_userno = $currency_info->auction_manager_userno;
if(!$manager_userno) {
	$tradeapi->error('113', __('Please set an auction manager.'));
}
$manager_wallet = $tradeapi->query_fetch_object("SELECT userno, symbol, confirmed, locked, autolocked, account, address FROM js_exchange_wallet WHERE userno='{$manager_userno}' AND symbol='{$tradeapi->escape($symbol)}' ");
if(!$manager_wallet) {
	$address = $tradeapi->create_wallet($manager_userno, $symbol);
	if(!$address) {
		$tradeapi->write_log( "manager_wallet이 없어 생성하려 했지만 실패했습니다. manager_wallet: {$manager_wallet}, manager_userno: {$manager_userno}, auction_idx: {$auction_idx}");
		$tradeapi->error('114', __('경매 관리자 지갑을 생성하지 못했습니다.'));
	}
	$tradeapi->save_wallet($manager_userno, $symbol, $address, 0);
	if($currency_info->base_coin) {
		$tradeapi->save_wallet($manager_userno, $currency_info->base_coin, $address, 0);
	}
	// $tradeapi->error('112', __('Please set th auction manager wallet.'));
}

$tradeapi->transaction_start();

// 전체 추가금액 = 추가금액 + 추가 수수료;
$total_add_amount = $add_amount + $add_fee; 

// 잔액 가/감액
if($total_add_amount > 0) { // 잔액 감액
	$tradeapi->query("UPDATE js_exchange_wallet SET confirmed = confirmed - {$tradeapi->escape($total_add_amount)} WHERE userno='{$tradeapi->escape($userno)}' AND symbol='{$tradeapi->escape($symbol)}'  ");
	$sql = "INSERT INTO js_exchange_wallet_txn SET `userno`='{$tradeapi->escape($userno)}', `symbol`='{$tradeapi->escape($symbol)}', `address`='{$tradeapi->escape($user_wallet->address)}', `regdate`=NOW(), `txndate`=NOW(), `address_relative`='{$tradeapi->escape($manager_wallet->address)}', `txn_type`='AB', `direction`='O', `amount`='{$tradeapi->escape($add_amount)}', `fee`='{$tradeapi->escape($add_fee)}', `tax`=0, `status`='D', `key_relative`='{$tradeapi->escape($auction_idx)}', `txn_method`='COIN', app_no='".__APP_NO__."', `msg`='bidding' ";
}
if($total_add_amount<0) { // 잔액 가액
	$tradeapi->query("UPDATE js_exchange_wallet SET confirmed = confirmed + {$tradeapi->escape(abs($total_add_amount))} WHERE userno='{$tradeapi->escape($userno)}' AND symbol='{$tradeapi->escape($symbol)}'  ");
	$sql = "INSERT INTO js_exchange_wallet_txn SET `userno`='{$tradeapi->escape($userno)}', `symbol`='{$tradeapi->escape($symbol)}', `address`='{$tradeapi->escape($user_wallet->address)}', `regdate`=NOW(), `txndate`=NOW(), `address_relative`='{$tradeapi->escape($manager_wallet->address)}', `txn_type`='AB', `direction`='I', `amount`='{$tradeapi->escape(abs($add_amount))}', `fee`='{$tradeapi->escape(abs($add_fee))}', `tax`=0, `status`='D', `key_relative`='{$tradeapi->escape($auction_idx)}', `txn_method`='COIN', app_no='".__APP_NO__."', `msg`='bidding' ";
}
$tradeapi->query($sql);
// 관리자계정 증액
if($total_add_amount > 0) { // 잔액 가액
	$tradeapi->query("UPDATE js_exchange_wallet SET confirmed = confirmed + {$tradeapi->escape($total_add_amount)} WHERE userno='{$manager_userno}' AND symbol='{$tradeapi->escape($symbol)}'  ");
	$sql = "INSERT INTO js_exchange_wallet_txn SET `userno`='{$manager_userno}', `symbol`='{$tradeapi->escape($symbol)}', `address`='{$tradeapi->escape($manager_wallet->address)}', `regdate`=NOW(), `txndate`=NOW(), `address_relative`='{$tradeapi->escape($user_wallet->address)}', `txn_type`='AB', `direction`='I', `amount`='{$tradeapi->escape($total_add_amount)}', `fee`='0', `tax`=0, `status`='D', `key_relative`='{$tradeapi->escape($auction_idx)}', `txn_method`='COIN', app_no='".__APP_NO__."', `msg`='bidding' ";
	// 수수료는 없이 총 추가한 금액만 amount에 합산해 추가합니다. 계산 혼돈하지 않기위해.
}
if($total_add_amount<0) { // 잔액 감액
	$tradeapi->query("UPDATE js_exchange_wallet SET confirmed = confirmed - {$tradeapi->escape(abs($total_add_amount))} WHERE userno='{$manager_userno}' AND symbol='{$tradeapi->escape($symbol)}'  ");
	$sql = "INSERT INTO js_exchange_wallet_txn SET `userno`='{$manager_userno}', `symbol`='{$tradeapi->escape($symbol)}', `address`='{$tradeapi->escape($manager_wallet->address)}', `regdate`=NOW(), `txndate`=NOW(), `address_relative`='{$tradeapi->escape($user_wallet->address)}', `txn_type`='AB', `direction`='O', `amount`='{$tradeapi->escape(abs($total_add_amount))}', `fee`='0', `tax`=0, `status`='D', `key_relative`='{$tradeapi->escape($auction_idx)}', `txn_method`='COIN', app_no='".__APP_NO__."', `msg`='bidding' ";
	// 수수료는 없이 총 추가한 금액만 amount에 합산해 추가합니다. 계산 혼돈하지 않기위해.
}
$tradeapi->query($sql);

//옥션 등록, 존재 하면 금액 추가(재 apply)
if($user_bid) {
	$tradeapi->query("UPDATE js_auction_apply SET auction_price='{$tradeapi->escape($bid_price)}', auction_volume='{$tradeapi->escape($bid_volume)}', auction_amount='{$tradeapi->escape($bid_amount)}', auction_fee='{$tradeapi->escape($bid_fee)}', mod_date=NOW() WHERE auction_idx='{$tradeapi->escape($auction_idx)}' AND goods_idx='{$tradeapi->escape($auction_info->goods_idx)}' AND userno='{$tradeapi->escape($userno)}' AND `status`='P' ");
} else {
	$tradeapi->query("INSERT INTO js_auction_apply SET auction_idx='{$tradeapi->escape($auction_idx)}', goods_idx='{$tradeapi->escape($auction_info->goods_idx)}', userid='{$tradeapi->escape($userid)}', userno='{$tradeapi->escape($userno)}', auction_price='{$tradeapi->escape($bid_price)}', auction_volume='{$tradeapi->escape($bid_volume)}', auction_amount='{$tradeapi->escape($bid_amount)}', auction_fee='{$tradeapi->escape($bid_fee)}', reg_date=NOW(), `status`='P'  ");
}
$bid_idx = $tradeapi->gen_id();

// 입찰 로그 작성
$tradeapi->query("INSERT INTO js_auction_apply_list SET bid_idx='{$bid_idx}', auction_idx='{$tradeapi->escape($auction_idx)}', userno='{$tradeapi->escape($userno)}', userid='{$tradeapi->escape($userid)}', auction_price='{$tradeapi->escape($bid_price)}', auction_volume='{$tradeapi->escape($bid_volume)}', auction_amount='{$tradeapi->escape($bid_amount)}', auction_fee='{$tradeapi->escape($bid_fee)}', reg_date=NOW(), apply_type='B' ");

// 경매입찰수 증가
$tradeapi->query("UPDATE js_auction_list SET cnt_bid=cnt_bid+1 where auction_idx='{$tradeapi->escape($auction_info->auction_idx)}' ");

// 마지막 입찰가격 상품 정보에 저장
$tradeapi->query("UPDATE js_auction_goods SET price='{$tradeapi->escape($bid_price)}' where idx='{$tradeapi->escape($auction_info->goods_idx)}' ");

$tradeapi->transaction_end('commit');
$tradeapi->success();

