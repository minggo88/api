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
$buyer_userno = $userno;
$buyer_userid = $userid;

$auction_idx= checkEmpty($_REQUEST['auction_idx'], 'auction_idx');  //auction idx
// $bid_price      = checkNumber(checkEmpty($_REQUEST['price'], 'price'));          //입찰금액 - 증액분을 받아 계산했었는데... 동시에 들어오면 입찰시 보여진 금액과 차이가 나기때문에 최종 입찰금액을 받아 처리하는거으로 변경.
$bid_volume      = checkNumber(setDefault($_REQUEST['volume'], '1'));          //구매수량

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('master');

//경매 정보 확인
// $query = "SELECT g.idx FROM js_auction_list as a inner join js_auction_goods as g on a.goods_idx=g.idx
//     WHERE a.auction_idx='{$tradeapi->escape($auction_idx)}' and now()>=a.start_date and now()<=a.end_date";
// $get_auction_goods = $tradeapi->query_one($query);
$query = "SELECT auction_idx, goods_idx, start_date, end_date, price_symbol, wish_price, creator_userno, sell_volume FROM js_auction_list WHERE auction_idx='{$tradeapi->escape($auction_idx)}' ";
$auction_info = $tradeapi->query_fetch_object($query);
$seller_userno = $auction_info->creator_userno;
$seller_userid = $tradeapi->query_one("SELECT userid FROM js_member WHERE userno='{$tradeapi->escape($seller_userno)}'");
// if(!$auction_info){
//     $tradeapi->error('105', __('No auction now.').$query);
// }
// if($auction_info->start_date && $auction_info->start_date>date('Y-m-d H:i:s')) {
// 	$tradeapi->error('106', __('경매가 시작되지 않았습니다.').' '.__('시작날짜를 확인해주세요.'));
// }
// if($auction_info->end_date && $auction_info->end_date<date('Y-m-d H:i:s')) {
// 	$tradeapi->error('107', __('경매가 종료되었습니다.').' '.__('다른 경매상품에 참여해주세요.'));
// }
// if($auction_info->finish && $auction_info->finish=='Y') {
// 	$tradeapi->error('108', __('경매가 종료되었습니다.').' '.__('다른 경매상품에 참여해주세요.'));
// }
// 경매 상품 정보
$goods_info = $tradeapi->query_fetch_object("SELECT g.idx goods_idx, g.*  FROM js_auction_goods g WHERE idx='{$tradeapi->escape($auction_info->goods_idx)}' ");


$sell_goods_info = $goods_info; // 판매 상품
$sellable_goods_cnt = 0;				// 남은 판매 상품수
$finish = 'Y';									// 경매 종료 처리 여부

// 패키지 상품인 경우 서브 상품중 하나 선택 - 먼저 생성한것부터 판매.
if($goods_info->pack_info=='Y') { // 패키지 상품인경우 판매상품(서브상품)중 하나를 선택합니다.
	$sell_goods_info = $tradeapi->query_fetch_object("SELECT g.idx goods_idx, g.*  FROM js_auction_goods g WHERE pack_info='{$tradeapi->escape($goods_info->goods_idx)}' ORDER BY idx LIMIT 1 ");
	if(!$sell_goods_info) {
		$tradeapi->error('109', __('판매 가능한 상품이 없습니다.'));
	}
	// 남은 수량 확인 - 경매를 종료처리해야할지 확인하기 위함.
	$sellable_goods_cnt = $tradeapi->query_one("SELECT COUNT(*)  FROM js_auction_goods g WHERE pack_info='{$tradeapi->escape($goods_info->goods_idx)}' ");
	if($sellable_goods_cnt>0) {$finish = 'N';}
}
// 분할판매상품인경우 ..
if($auction_info->sell_volume>1) {
	// 남은 수량 및 주문 수량 확인
	$sold_volume = $tradeapi->query_one("SELECT IFNULL(SUM(auction_volume),0) FROM js_auction_apply WHERE auction_idx='{$tradeapi->escape($auction_idx)}' AND status='S'");
	$sellable_goods_cnt = $auction_info->sell_volume - $sold_volume;
	if($sellable_goods_cnt<$bid_volume) {
		$tradeapi->error('108', __('Please enter the order quantity below the remain quantity.'));
	}
	// 남은 수량 확인 - 경매를 종료처리해야할지 확인하기 위함.
	if($sellable_goods_cnt>$bid_volume) {$finish = 'N';}
}

// $auction_info->price_symbol = $auction_info->price_symbol; // 입찰 화폐
$currency_info = $tradeapi->query_fetch_object("SELECT base_coin, auction_manager_userno, display_decimals FROM js_exchange_currency ec LEFT JOIN js_auction_currency ac ON ec.symbol=ac.symbol WHERE ec.symbol='{$tradeapi->escape($auction_info->price_symbol)}' ");

//자신것은 입찰 못하게 막는다
// $query = "SELECT userid FROM js_auction_inventory WHERE userno='{$buyer_userno}' and goods_idx='{$tradeapi->escape($auction_info->goods_idx)}'";
// $chk_owner = $tradeapi->query_one($query);
// if($chk_owner){
//     $tradeapi->error('109', __('You can\'t apply your goods.'));
// }
if($seller_userno == $buyer_userno){
    $tradeapi->error('109-1', __('You can\'t apply your goods.'));
}

//제품 최대 금액 확인
$query = "SELECT max(auction_price) as max_price FROM js_auction_apply WHERE auction_idx='{$tradeapi->escape($auction_idx)}' AND `status`='P'";
$max_price = $tradeapi->query_one($query);
if($max_price >= $auction_info->wish_price){
	$tradeapi->error('110', __('입찰가격이 높아져 바로구매는 비활성화 되었습니다.'));
}
$bid_price = $auction_info->wish_price; //입찰가격 = 바로구매가격
// $bid_volume      = checkNumber(setDefault($_REQUEST['volume'], '1')); // 입찰수량
$bid_amount      = checkNumber(setDefault($_REQUEST['amount'], $bid_price*$bid_volume)); // 입찰금액

$auction_fee = $tradeapi->query_fetch_object("SELECT fee_buy, fee_sell FROM js_config_auction WHERE code='{$tradeapi->escape($tradeapi->get_site_code())}'");
// 판매 수수료
$sell_fee = $tradeapi->cal_percent_fee($auction_fee->fee_sell, $bid_amount, $currency_info->display_decimals);
// 입찰 수수료
$bid_fee = $tradeapi->cal_percent_fee($auction_fee->fee_buy, $bid_amount, $currency_info->display_decimals);
// var_dump($auction_fee->fee_buy, $bid_amount, $currency_info->display_decimals, '$bid_fee:'.$bid_fee, '$sell_fee:'.$sell_fee); //exit;

// royalty 계산
$sell_royalty = $tradeapi->cal_percent_royalty($goods_info->royalty, $bid_amount, $currency_info->display_decimals); // 로열티 판매자에게 과금
// $sell_royalty = $tradeapi->cal_percent_royalty($goods_info->royalty_sell, $bid_amount, $currency_info->display_decimals); // 판매자 로열티 분리시 - 미사용
// $buy_royalty = $tradeapi->cal_percent_royalty($goods_info->royalty_buy, $bid_amount, $currency_info->display_decimals); // 구매자 로열티 분리시 - 미사용


//사용자 입찰 금액 확인
$query = "SELECT auction_price as user_price, auction_amount as user_amount, auction_fee as user_fee FROM js_auction_apply WHERE auction_idx='{$tradeapi->escape($auction_idx)}' AND goods_idx='{$tradeapi->escape($auction_info->goods_idx)}' and userno='{$tradeapi->escape($buyer_userno)}' AND `status`='P' ";
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
$user_wallet = $tradeapi->query_fetch_object("SELECT userno, symbol, confirmed, locked, autolocked, account, address FROM js_exchange_wallet WHERE userno='{$tradeapi->escape($buyer_userno)}' AND symbol='{$tradeapi->escape($auction_info->price_symbol)}' ");
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
$manager_wallet = $tradeapi->query_fetch_object("SELECT userno, symbol, confirmed, locked, autolocked, account, address FROM js_exchange_wallet WHERE userno='{$manager_userno}' AND symbol='{$tradeapi->escape($auction_info->price_symbol)}' ");
if(!$manager_wallet) {
	$address = $tradeapi->create_wallet($manager_userno, $auction_info->price_symbol);
	if(!$address) {
		$tradeapi->write_log( "manager_wallet이 없어 생성하려 했지만 실패했습니다. manager_wallet: {$manager_wallet}, manager_userno: {$manager_userno}, auction_idx: {$auction_idx}");
		$tradeapi->error('114', __('경매 관리자 지갑을 생성하지 못했습니다.'));
	}
	$tradeapi->save_wallet($manager_userno, $auction_info->price_symbol, $address, 0);
	if($currency_info->base_coin) {
		$tradeapi->save_wallet($manager_userno, $currency_info->base_coin, $address, 0);
	}
	// $tradeapi->error('112', __('Please set the auction manager wallet.'));
}

$tradeapi->transaction_start();

// 전체 추가금액 = 추가금액 + 추가 수수료;
$total_add_amount = $add_amount + $add_fee; 
// $total_add_amount = $add_amount + $add_fee + $buy_royalty;  // 구매자에게도 로열티를 과금시 - 미사용

// 잔액 가/감액
if($total_add_amount > 0) { // 잔액 감액
	// 쿼리에 $add_amount, $add_fee를 남기기위해 $total_add_amount 를 사용하지 않습니다.
	$tradeapi->query("UPDATE js_exchange_wallet SET confirmed = confirmed - {$tradeapi->escape($total_add_amount)} WHERE userno='{$tradeapi->escape($buyer_userno)}' AND symbol='{$tradeapi->escape($auction_info->price_symbol)}'  ");
	$sql = "INSERT INTO js_exchange_wallet_txn SET `userno`='{$tradeapi->escape($buyer_userno)}', `symbol`='{$tradeapi->escape($auction_info->price_symbol)}', `address`='{$tradeapi->escape($user_wallet->address)}', `regdate`=NOW(), `txndate`=NOW(), `address_relative`='{$tradeapi->escape($manager_wallet->address)}', `txn_type`='AD', `direction`='O', `amount`='{$tradeapi->escape($add_amount)}', `fee`='{$tradeapi->escape($add_fee)}', `tax`=0, `status`='D', `key_relative`='{$tradeapi->escape($auction_idx)}', `txn_method`='COIN', app_no='".__APP_NO__."', `msg`='buy' ";
}
if($total_add_amount<0) { // 잔액 가액
	$tradeapi->query("UPDATE js_exchange_wallet SET confirmed = confirmed + {$tradeapi->escape(abs($total_add_amount))} WHERE userno='{$tradeapi->escape($seller_userno)}' AND symbol='{$tradeapi->escape($auction_info->price_symbol)}'  ");
	$sql = "INSERT INTO js_exchange_wallet_txn SET `userno`='{$tradeapi->escape($buyer_userno)}', `symbol`='{$tradeapi->escape($auction_info->price_symbol)}', `address`='{$tradeapi->escape($user_wallet->address)}', `regdate`=NOW(), `txndate`=NOW(), `address_relative`='{$tradeapi->escape($manager_wallet->address)}', `txn_type`='AD', `direction`='I', `amount`='{$tradeapi->escape(abs($add_amount))}', `fee`='{$tradeapi->escape(abs($add_fee))}', `tax`=0, `status`='D', `key_relative`='{$tradeapi->escape($auction_idx)}', `txn_method`='COIN', app_no='".__APP_NO__."', `msg`='buy' ";
}
$tradeapi->query($sql);
// 관리자계정 증액
if($total_add_amount > 0) { // 잔액 가액
	$tradeapi->query("UPDATE js_exchange_wallet SET confirmed = confirmed + {$tradeapi->escape($total_add_amount)} WHERE userno='{$manager_userno}' AND symbol='{$tradeapi->escape($auction_info->price_symbol)}'  ");
	$sql = "INSERT INTO js_exchange_wallet_txn SET `userno`='{$manager_userno}', `symbol`='{$tradeapi->escape($auction_info->price_symbol)}', `address`='{$tradeapi->escape($manager_wallet->address)}', `regdate`=NOW(), `txndate`=NOW(), `address_relative`='{$tradeapi->escape($user_wallet->address)}', `txn_type`='AD', `direction`='I', `amount`='{$tradeapi->escape($total_add_amount)}', `fee`='0', `tax`=0, `status`='D', `key_relative`='{$tradeapi->escape($auction_idx)}', `txn_method`='COIN', app_no='".__APP_NO__."', `msg`='buy' ";
	// 수수료는 없이 총 추가한 금액만 amount에 합산해 추가합니다. 계산 혼돈하지 않기위해.
}
if($total_add_amount<0) { // 잔액 감액
	$tradeapi->query("UPDATE js_exchange_wallet SET confirmed = confirmed - {$tradeapi->escape(abs($total_add_amount))} WHERE userno='{$manager_userno}' AND symbol='{$tradeapi->escape($auction_info->price_symbol)}'  ");
	$sql = "INSERT INTO js_exchange_wallet_txn SET `userno`='{$manager_userno}', `symbol`='{$tradeapi->escape($auction_info->price_symbol)}', `address`='{$tradeapi->escape($manager_wallet->address)}', `regdate`=NOW(), `txndate`=NOW(), `address_relative`='{$tradeapi->escape($user_wallet->address)}', `txn_type`='AD', `direction`='O', `amount`='{$tradeapi->escape(abs($total_add_amount))}', `fee`='0', `tax`=0, `status`='D', `key_relative`='{$tradeapi->escape($auction_idx)}', `txn_method`='COIN', app_no='".__APP_NO__."', `msg`='buy' ";
	// 수수료는 없이 총 추가한 금액만 amount에 합산해 추가합니다. 계산 혼돈하지 않기위해.
}
$tradeapi->query($sql);

//옥션 등록, 존재 하면 금액 추가(재 apply)
if($goods_info->pack_info=='Y') {
	// 패키지 서브 상품 바로구매 내역 저장.
	$tradeapi->query("INSERT INTO js_auction_apply (auction_idx ,goods_idx, userid, userno, auction_price, auction_volume, auction_amount, auction_fee, reg_date, `status`) VALUES ('{$tradeapi->escape($auction_idx)}','{$tradeapi->escape($sell_goods_info->goods_idx)}','{$tradeapi->escape($buyer_userid)}','{$tradeapi->escape($buyer_userno)}','{$tradeapi->escape($bid_price)}','{$tradeapi->escape($bid_volume)}','{$tradeapi->escape($bid_amount)}','{$tradeapi->escape($bid_fee)}', now(), 'S') ");
}
// 옥션 상품 바로구매 내역 저장
if($user_bid) { // 이전에 입찰한 내역이 있으면 해당 내역을 완료처리 변경.
	$re = $tradeapi->query("UPDATE js_auction_apply SET auction_price='{$tradeapi->escape($bid_price)}', auction_volume='{$tradeapi->escape($bid_volume)}', auction_amount='{$tradeapi->escape($bid_amount)}', auction_fee='{$tradeapi->escape($bid_fee)}', mod_date=NOW(), `status`='S' WHERE auction_idx='{$tradeapi->escape($auction_idx)}' AND goods_idx='{$tradeapi->escape($auction_info->goods_idx)}' AND userno='{$tradeapi->escape($buyer_userno)}' AND `status`='P' ");
} else { // 이전에 입찰한 내역이 없으면 새로 완료된 내역 추가
	$re = $tradeapi->query("INSERT INTO js_auction_apply SET auction_idx='{$tradeapi->escape($auction_idx)}', goods_idx='{$tradeapi->escape($auction_info->goods_idx)}', userid='{$tradeapi->escape($buyer_userid)}', userno='{$tradeapi->escape($buyer_userno)}', auction_price='{$tradeapi->escape($bid_price)}', auction_volume='{$tradeapi->escape($bid_volume)}', auction_amount='{$tradeapi->escape($bid_amount)}', auction_fee='{$tradeapi->escape($bid_fee)}', reg_date=NOW(), `status`='S'  ");
}


if($re){

	// 남은 수량 - 수량 처리 미적용. 경매 등록시 판매 수량을 등록해야 함.
	// 남은 수량 계산방식으로 변경. DB컬럼 없음.

	$bid_idx = $tradeapi->gen_id();
	// 입찰 로그 작성
	$tradeapi->query("INSERT INTO js_auction_apply_list SET bid_idx='{$bid_idx}', auction_idx='{$tradeapi->escape($auction_idx)}', userno='{$tradeapi->escape($buyer_userno)}', userid='{$tradeapi->escape($buyer_userid)}', auction_price='{$tradeapi->escape($bid_price)}', auction_volume='{$tradeapi->escape($bid_volume)}', auction_amount='{$tradeapi->escape($bid_amount)}', auction_fee='{$tradeapi->escape($bid_fee)}', reg_date=NOW(), apply_type='D' ");

	// NFT 소유자 변경. js_auction_inventory, js_auction_goods
	if($goods_info->nft_max_supply>1) { // 분할된 상품이면 소유자를 추가하고 이전 소유자에서 제거해야함.
		// 소유자 인벤토리에서 제거
		$seller_inventory_info = $tradeapi->query_fetch_object("SELECT * FROM js_auction_inventory WHERE goods_idx='{$tradeapi->escape($sell_goods_info->goods_idx)}' AND userno='{$tradeapi->escape($seller_userno)}' ");
		if($seller_inventory_info->amount - $bid_volume < 0) {
			$tradeapi->error('201', __('구매 수량이 남은 수량보다 많습니다.'));
		}
		if($seller_inventory_info->amount - $bid_volume == 0) {
			$tradeapi->query_fetch_object("DELETE FROM js_auction_inventory WHERE goods_idx='{$tradeapi->escape($sell_goods_info->goods_idx)}' AND userno='{$tradeapi->escape($seller_userno)}' ");
		}
		if($seller_inventory_info->amount - $bid_volume > 0) {
			$tradeapi->query_fetch_object("UPDATE js_auction_inventory SET amount = amount - $bid_volume  WHERE goods_idx='{$tradeapi->escape($sell_goods_info->goods_idx)}' AND userno='{$tradeapi->escape($seller_userno)}' ");
		}
		// 구매자  인벤토리에 추가
		$buyer_inventory_info = $tradeapi->query_fetch_object("SELECT * FROM js_auction_inventory WHERE goods_idx='{$tradeapi->escape($sell_goods_info->goods_idx)}' AND userno='{$tradeapi->escape($buyer_userno)}' ");
		if($buyer_inventory_info) {
			$tradeapi->query_fetch_object("UPDATE js_auction_inventory SET amount = amount + $bid_volume  WHERE goods_idx='{$tradeapi->escape($sell_goods_info->goods_idx)}' AND userno='{$tradeapi->escape($buyer_userno)}' ");
		} else {
			$tradeapi->query_fetch_object("INSERT INTO js_auction_inventory SET amount = $bid_volume, goods_idx='{$tradeapi->escape($sell_goods_info->goods_idx)}', userno='{$tradeapi->escape($buyer_userno)}', userid='{$tradeapi->escape($buyer_userid)}', buy_price='{$tradeapi->escape($bid_price)}', buy_auction_idx='{$tradeapi->escape($auction_idx)}', reg_date=NOW() ");
		}

		// js_exchange_walelt_txn용 매매량(amount) 수치
		// $txn_amount = numtostr($bid_volume / $goods_info->nft_max_supply);
		$txn_amount = $bid_volume;

	} else { // 미분할 상품은 소유자 정보를 바꾸는 것으로 처리
		$tradeapi->query("UPDATE js_auction_goods SET owner_userno='{$tradeapi->escape($buyer_userno)}', pack_info='N' where idx='{$tradeapi->escape($sell_goods_info->goods_idx)}' ");
		$inventory_info = $tradeapi->query_fetch_object("SELECT * FROM js_auction_inventory WHERE goods_idx='{$tradeapi->escape($sell_goods_info->goods_idx)}' ");
		if($inventory_info) {
			$tradeapi->query("UPDATE js_auction_inventory SET userno='{$tradeapi->escape($buyer_userno)}', userid='{$tradeapi->escape($buyer_userid)}', buy_price='{$tradeapi->escape($bid_price)}', buy_auction_idx='{$tradeapi->escape($auction_idx)}', reg_date=NOW() WHERE goods_idx='{$tradeapi->escape($sell_goods_info->goods_idx)}' ");
		} else {
			$tradeapi->query("INSERT INTO js_auction_inventory SET userno='{$tradeapi->escape($buyer_userno)}', userid='{$tradeapi->escape($buyer_userid)}', goods_idx='{$tradeapi->escape($sell_goods_info->goods_idx)}', buy_price='{$tradeapi->escape($bid_price)}', buy_auction_idx='{$tradeapi->escape($auction_info->auction_idx)}', reg_date=NOW() ");
		}

		// js_exchange_walelt_txn용 매매량(amount) 수치
		$txn_amount = 1;
	}

	// nft_id
	$nft_id = $tradeapi->query_one("SELECT nft_id from js_auction_goods where idx='{$tradeapi->escape($sell_goods_info->goods_idx)}' ");

	// 매도자 정보
	$seller_wallet = $tradeapi->query_fetch_object("SELECT * from js_exchange_wallet where userno='{$tradeapi->escape($seller_userno)}' AND symbol='{$tradeapi->escape($goods_info->nft_symbol)}'");

	// 매도자 회원 지갑에 토큰 정보 저장
	$tradeapi->query("UPDATE js_exchange_wallet SET confirmed=confirmed-{$tradeapi->escape($txn_amount)} WHERE userno='{$tradeapi->escape($seller_userno)}' AND symbol='{$tradeapi->escape($goods_info->nft_symbol)}'"); // 보유량 -1
	$tradeapi->query("INSERT INTO js_exchange_wallet_txn SET userno='{$tradeapi->escape($seller_userno)}', symbol='{$tradeapi->escape($goods_info->nft_symbol)}', address='{$tradeapi->escape($seller_wallet->address)}', regdate=NOW(), txndate=NOW(), address_relative='{$tradeapi->escape($user_wallet->address)}', txn_type='AD', direction='O', nft_id='{$tradeapi->escape($nft_id)}', amount='{$tradeapi->escape($txn_amount)}', fee='0', tax='0', status='D', service_name='AUCTION', key_relative='', txn_method='COIN', app_no='".__APP_NO__."', msg='buy' "); // , [owner_info[0].userno, goods_info[0].nft_symbol, receiver_address, '0x0000000000000000000000000000000000000000', nftid, r.transactionHash, JSON.stringify(r)]
	$seller_wallet_nft_info = $tradeapi->query_fetch_object("SELECT * FROM js_exchange_wallet_nft WHERE `symbol`='{$tradeapi->escape($goods_info->nft_symbol)}' AND `tokenid`='{$nft_id}' AND `userno`='{$seller_userno}'");
	if($seller_wallet_nft_info->amount < $bid_volume) {
		$tradeapi->query("DELETE FROM js_exchange_wallet_nft WHERE `symbol`='{$tradeapi->escape($goods_info->nft_symbol)}' AND `tokenid`='{$nft_id}' AND `userno`='{$seller_userno}' ");
	} else {
		$tradeapi->query("UPDATE js_exchange_wallet_nft SET amount = amount - $txn_amount, `mode_date`=NOW() WHERE `symbol`='{$tradeapi->escape($goods_info->nft_symbol)}' AND `tokenid`='{$nft_id}' AND `userno`='{$seller_userno}' ");
	}

	// 매수자 회원 지갑에 토큰 정보 저장
	$tradeapi->query("UPDATE js_exchange_wallet SET confirmed=confirmed+{$tradeapi->escape($txn_amount)} WHERE userno='{$tradeapi->escape($buyer_userno)}' AND symbol='{$tradeapi->escape($goods_info->nft_symbol)}'"); // 보유량 +1
	$tradeapi->query("INSERT INTO js_exchange_wallet_txn SET userno='{$tradeapi->escape($buyer_userno)}', symbol='{$tradeapi->escape($goods_info->nft_symbol)}', address='{$tradeapi->escape($user_wallet->address)}', regdate=NOW(), txndate=NOW(), address_relative='{$tradeapi->escape($seller_wallet->address)}', txn_type='AD', direction='I', nft_id='{$tradeapi->escape($nft_id)}', amount='{$tradeapi->escape($txn_amount)}', fee='0', tax='0', status='D', service_name='AUCTION', key_relative='', txn_method='COIN', app_no='".__APP_NO__."', msg='buy' "); // , [owner_info[0].userno, goods_info[0].nft_symbol, receiver_address, '0x0000000000000000000000000000000000000000', nftid, r.transactionHash, JSON.stringify(r)]
	$buyer_wallet_nft_info = $tradeapi->query_fetch_object("SELECT * FROM js_exchange_wallet_nft WHERE `symbol`='{$tradeapi->escape($goods_info->nft_symbol)}' AND `tokenid`='{$nft_id}' AND `userno`='{$buyer_userno}'");
	if($buyer_wallet_nft_info) {
		$tradeapi->query("UPDATE js_exchange_wallet_nft SET amount = amount + $txn_amount, `mode_date`=NOW() WHERE `symbol`='{$tradeapi->escape($goods_info->nft_symbol)}' AND `tokenid`='{$nft_id}' AND `userno`='{$buyer_userno}'"); // amount: 실 입금액, fee: 차감한 수수료.
	} else {
		$tradeapi->query("INSERT INTO js_exchange_wallet_nft SET `symbol`='{$tradeapi->escape($goods_info->nft_symbol)}', `tokenid`='{$nft_id}', `userno`='{$buyer_userno}', `amount`='{$tradeapi->escape($txn_amount)}', `reg_date`=NOW() "); // amount: 실 입금액, fee: 차감한 수수료.
	}

	// 옥션 상품 거래내역 저장 txn_type 바로구매: D, 낙찰: B
	$microtime = $tradeapi->gen_microtime();
    $tradeapi->query("INSERT INTO `js_auction_txn` SET reg_time='{$tradeapi->escape($microtime)}', goods_idx='{$tradeapi->escape($sell_goods_info->goods_idx)}', symbol='{$tradeapi->escape($goods_info->nft_symbol)}', tokenid='{$tradeapi->escape($nft_id)}', txnid='', status='S', txn_type='D', sender_address='{$tradeapi->escape($seller_wallet->address)}', receiver_address='{$tradeapi->escape($user_wallet->address)}', price_symbol='{$tradeapi->escape($auction_info->price_symbol)}', price='{$tradeapi->escape($bid_price)}', amount='{$tradeapi->escape($txn_amount)}', fee='{$tradeapi->escape($sell_fee)}', tax='', royalty='{$tradeapi->escape($sell_royalty)}', message='', check_time='', sender_userno='{$tradeapi->escape($seller_userno)}', receiver_userno='{$tradeapi->escape($buyer_userno)}', shop_id='', order_id='', relation_data='', auction_idx='{$tradeapi->escape($auction_idx)}'   ");

	// 경매입찰수 증가, 경매 종료처리.
	if($finish=='Y') {
		$tradeapi->query("UPDATE js_auction_list SET cnt_bid=cnt_bid+1, buyer_userno='{$tradeapi->escape($buyer_userno)}', sell_price='{$tradeapi->escape($bid_price)}', finish='Y', finish_date=NOW(), mod_date=NOW() where auction_idx='{$tradeapi->escape($auction_idx)}' ");
	}

	// 마지막 입찰가격 상품 정보에 저장
	if($finish=='Y') {
		$finish_price = $bid_price; // 마지막 구매가
		// // 여러개를 판매한경우 평균가격을 계산
		// if($goods_info->nft_max_supply>1) {
		// 	$finish_price = $tradeapi->query_one("SELECT SUM(auction_amount)/SUM(auction_volume) FROM js_auction_apply WHERE auction_idx='{$tradeapi->escape($auction_idx)}' AND status='S' ");
		// }
		// if($goods_info->nft_max_supply>1) {
		// 	$finish_price = $tradeapi->query_one("SELECT SUM(auction_amount)/SUM(auction_volume) FROM js_auction_apply WHERE auction_idx='{$tradeapi->escape($auction_idx)}' AND status='S' ");
		// }
		$tradeapi->query("UPDATE js_auction_goods SET price='{$tradeapi->escape($finish_price)}' where idx='{$tradeapi->escape($sell_goods_info->goods_idx)}' ");
	}

	// 낙찰 처리 - 구매자입찰 정보 낙찰 처리
	$tradeapi->query("UPDATE js_auction_apply SET status='S' WHERE auction_idx='{$tradeapi->escape($auction_idx)}' AND userno='{$tradeapi->escape($buyer_userno)}' AND `status`='P' ");

	// 판매수령액 
	// 판매자에게 판매금액(바로구매금액 - 판매수수료) 지급 $seller_wallet $seller_userno
	//$sell_amount = numtostr($bid_amount - $sell_fee); // 판매로 받을 실 금액. 로열티 미적용시
	if($sell_royalty && $goods_info->creator_userno && $goods_info->creator_userno != $seller_userno) {
		$sell_amount = numtostr($bid_amount - $sell_fee - $sell_royalty); // 판매로 받을 실 금액. 로열티 적용시
	} else {
		$sell_amount = numtostr($bid_amount - $sell_fee); // 판매로 받을 실 금액. 로열티 없거나 받을 사람이 없는경우
	}
	$tradeapi->query("UPDATE js_exchange_wallet SET confirmed = confirmed + {$tradeapi->escape($sell_amount)} WHERE userno='{$seller_userno}' AND symbol='{$tradeapi->escape($auction_info->price_symbol)}'  ");
    $tradeapi->query("INSERT INTO js_exchange_wallet_txn SET `userno`='{$seller_userno}', `symbol`='{$tradeapi->escape($auction_info->price_symbol)}', `address`='{$tradeapi->escape($seller_wallet->address)}', `regdate`=NOW(), `txndate`=NOW(), `address_relative`='{$tradeapi->escape($manager_wallet->address)}', `txn_type`='AS', `direction`='I', `amount`='{$tradeapi->escape($sell_amount)}', `fee`='{$tradeapi->escape($sell_fee)}', `royalty`='{$tradeapi->escape($sell_royalty)}', `tax`=0, `status`='D', `key_relative`='{$tradeapi->escape($auction_idx)}', `txn_method`='COIN', app_no='".__APP_NO__."', `msg`='buy' "); // amount: 실 입금액, fee: 차감한 수수료.

	// 관리자 지갑에서 판매금액 차감
	$tradeapi->query("UPDATE js_exchange_wallet SET confirmed = confirmed - {$tradeapi->escape($sell_amount)} WHERE userno='{$manager_userno}' AND symbol='{$tradeapi->escape($auction_info->price_symbol)}'  ");
	$tradeapi->query("INSERT INTO js_exchange_wallet_txn SET `userno`='{$manager_userno}', `symbol`='{$tradeapi->escape($auction_info->price_symbol)}', `address`='{$tradeapi->escape($manager_wallet->address)}', `regdate`=NOW(), `txndate`=NOW(), `address_relative`='{$tradeapi->escape($seller_wallet->address)}', `txn_type`='AS', `direction`='O', `amount`='{$tradeapi->escape($sell_amount)}', `fee`='0', `tax`=0, `status`='D', `key_relative`='{$tradeapi->escape($auction_idx)}', `txn_method`='COIN', app_no='".__APP_NO__."', `msg`='buy' "); // amount: 실 입금액, fee: 차감한 수수료.

	// 로열티 지급 - 창작자 정보가 있고, 창작자가 판매자가 아니고, 로열티 금액이 있을때 지급
	if($sell_royalty && $goods_info->creator_userno && $goods_info->creator_userno != $seller_userno) {
		// 창작자에게 로열티 지급
		$creator_wallet = $tradeapi->query_fetch_object("SELECT userno, symbol, confirmed, locked, autolocked, account, address FROM js_exchange_wallet WHERE userno='{$tradeapi->escape($goods_info->creator_userno)}' AND symbol='{$tradeapi->escape($auction_info->price_symbol)}' ");
		$tradeapi->query("UPDATE js_exchange_wallet SET confirmed = confirmed + {$tradeapi->escape($sell_royalty)} WHERE userno='{$tradeapi->escape($goods_info->creator_userno)}' AND symbol='{$tradeapi->escape($auction_info->price_symbol)}'  ");
		$tradeapi->query("INSERT INTO js_exchange_wallet_txn SET `userno`='{$goods_info->creator_userno}', `symbol`='{$tradeapi->escape($auction_info->price_symbol)}', `address`='{$tradeapi->escape($creator_wallet->address)}', `regdate`=NOW(), `txndate`=NOW(), `address_relative`='{$tradeapi->escape($manager_wallet->address)}', `txn_type`='RY', `direction`='I', `amount`='{$tradeapi->escape($sell_royalty)}', `fee`='0', `tax`='0', `royalty`='0', `status`='D', `key_relative`='{$tradeapi->escape($auction_idx)}', `txn_method`='COIN', app_no='".__APP_NO__."', `msg`='buy' "); // amount: 실 입금액, fee: 차감한 수수료.
		// 관리자 지갑에서 로열티 차감
		$tradeapi->query("UPDATE js_exchange_wallet SET confirmed = confirmed - {$tradeapi->escape($sell_royalty)} WHERE userno='{$manager_userno}' AND symbol='{$tradeapi->escape($auction_info->price_symbol)}'  ");
		$tradeapi->query("INSERT INTO js_exchange_wallet_txn SET `userno`='{$manager_userno}', `symbol`='{$tradeapi->escape($auction_info->price_symbol)}', `address`='{$tradeapi->escape($manager_wallet->address)}', `regdate`=NOW(), `txndate`=NOW(), `address_relative`='{$tradeapi->escape($creator_wallet->address)}', `txn_type`='RY', `direction`='O', `amount`='{$tradeapi->escape($sell_royalty)}', `fee`='0', `tax`='0', `royalty`='0', `status`='D', `key_relative`='{$tradeapi->escape($auction_idx)}', `txn_method`='COIN', app_no='".__APP_NO__."', `msg`='buy' "); // amount: 실 입금액, fee: 차감한 수수료.
	}

	// 관리자 지갑에서 판매수수료 추가 - 관리자 지갑에서 수수료를 제외한 sell_amount 만큼만 이동시켰기때문에 수수료를 여기서주면 중복으로 지급하게 된다. 
	// $tradeapi->query("UPDATE js_exchange_wallet SET confirmed = confirmed + {$tradeapi->escape($sell_fee)} WHERE userno='{$manager_userno}' AND symbol='{$tradeapi->escape($auction_info->price_symbol)}'  ");
	// $tradeapi->query("INSERT INTO js_exchange_wallet_txn SET `userno`='{$manager_userno}', `symbol`='{$tradeapi->escape($auction_info->price_symbol)}', `address`='{$tradeapi->escape($manager_wallet->address)}', `regdate`=NOW(), `txndate`=NOW(), `address_relative`='{$tradeapi->escape($seller_wallet->address)}', `txn_type`='AS', `direction`='I', `amount`='{$tradeapi->escape($sell_fee)}', `fee`='0', `tax`=0, `status`='D', `key_relative`='{$tradeapi->escape($auction_idx)}', `txn_method`='COIN', app_no='".__APP_NO__."', `msg`='buy' "); // amount: 실 입금액, fee: 차감한 수수료.


	// 경매 종료시 유찰처리합니다.
	if($finish=='Y') {

		// 유찰 처리 - 입찰자들에게 입찰금 되돌려주기.
		$bids_info = $tradeapi->query_list_object("SELECT userno, auction_amount, auction_fee FROM js_auction_apply WHERE auction_idx='{$tradeapi->escape($auction_idx)}' AND userno<>'{$tradeapi->escape($buyer_userno)}' AND status='P' ");
		foreach($bids_info as $bid) {
			$bid_amount = $bid->auction_amount;
			$bid_fee = $bid->auction_fee;
			$refund_amount = numtostr($bid_amount + $bid_fee);
			$bider_userno = $bid->userno;
			$bider_wallet = $tradeapi->query_fetch_object("SELECT userno, symbol, confirmed, locked, autolocked, account, address FROM js_exchange_wallet WHERE userno='{$bider_userno}' AND symbol='{$tradeapi->escape($auction_info->price_symbol)}' ");
			if($refund_amount>0) {
				// 잔액 증가
				$tradeapi->query("UPDATE js_exchange_wallet SET confirmed=confirmed + {$tradeapi->escape($refund_amount)} WHERE symbol='{$tradeapi->escape($auction_info->price_symbol)}' AND userno='{$tradeapi->escape($bider_userno)}'");
				// 로그 작성
				$tradeapi->query("INSERT INTO js_exchange_wallet_txn SET `userno`='{$bider_userno}', `symbol`='{$tradeapi->escape($auction_info->price_symbol)}', `address`='{$tradeapi->escape($bider_wallet->address)}', `regdate`=NOW(), `txndate`=NOW(), `address_relative`='{$tradeapi->escape($manager_wallet->address)}', `txn_type`='AR', `direction`='I', `amount`='{$tradeapi->escape($refund_amount)}', `fee`='0', `tax`=0, `status`='D', `key_relative`='{$tradeapi->escape($auction_idx)}', `txn_method`='COIN', app_no='".__APP_NO__."', `msg`='Auction Bid Refund' ");
				// 트렌젝션 종류. R:(외부)입금, B:백업(콜드스토리지), W:(외부)출금, D:배당, E:교환, A:출석체크, I:초대하기, S:보내기, P:결제(pay), BO:보너스, R:환불(refund), C:충전(Charge), GA:Game Action, DO: Donation(Fan), MI:MINT(NFT생성), AD:Auction Direct Buy, AB:Auction Bid, AF: Auction Fee(옥션수수료), AS: Auction Sell, AR: Auction (Bid) Refund
				// walletmanager 잔액 제거
				$tradeapi->query("UPDATE js_exchange_wallet SET confirmed=confirmed - {$tradeapi->escape($refund_amount)} WHERE symbol='{$tradeapi->escape($auction_info->price_symbol)}' AND userno='{$tradeapi->escape($manager_userno)}'");
				// 로그 작성
				$tradeapi->query("INSERT INTO js_exchange_wallet_txn SET `userno`='{$manager_userno}', `symbol`='{$tradeapi->escape($auction_info->price_symbol)}', `address`='{$tradeapi->escape($manager_wallet->address)}', `regdate`=NOW(), `txndate`=NOW(), `address_relative`='{$tradeapi->escape($bider_wallet->address)}', `txn_type`='AR', `direction`='O', `amount`='{$tradeapi->escape($refund_amount)}', `fee`='0', `tax`=0, `status`='D', `key_relative`='{$tradeapi->escape($auction_idx)}', `txn_method`='COIN', app_no='".__APP_NO__."', `msg`='Auction Bid Refund' ");
			}
		}

		// 패키지 상품일경우 패키지 상품 종료처리
		if($goods_info->pack_info=='Y') {
			$tradeapi->query("UPDATE js_auction_goods SET active='N', mod_date=NOW() where idx='{$tradeapi->escape($goods_info->goods_idx)}'");
		}

	}

	$tradeapi->transaction_end('commit');
    $tradeapi->success();

} else {
    $tradeapi->error('005', __('A system error has occurred.'));
}