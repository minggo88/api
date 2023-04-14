<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

$tradeapi->set_logging(true);
$tradeapi->set_log_dir(__dir__.'/../../log/'.basename(__dir__).'/');
$tradeapi->set_log_name(basename(__file__));
$tradeapi->write_log("REQUEST: " . json_encode($_REQUEST));

// 로그인 세션 확인.
// 입력 값이 없으면 로그인 확인하는것으로 변경
$creator_userno      = setDefault($_REQUEST['creator_userno'], '2');
$owner_userno      = checkEmpty($_REQUEST['owner_userno'], 'owner_userno');
$goods_idx      = checkEmpty($_REQUEST['goods_idx'], 'goods_idx');
$stock_number   = checkEmpty($_REQUEST['stock_number'], 'stock_number');
$goods_grade   = checkEmpty($_REQUEST['goods_grade'], 'goods_grade');

if($creator_userno) {
    $creator_info = $tradeapi->get_user_info($creator_userno);

    if(!$creator_info || !$creator_info->userno) {
        $tradeapi->error('200', '회원정보를 확인해주세요.');
    }
    $creatorid = $creator_info->userid;
    $creatorno = $creator_info->userno;
} else {
    $tradeapi->checkLogin();
    $creatorid = $tradeapi->get_login_userid();
    $creatorno = $tradeapi->get_login_userno();
}

$owner_info = $tradeapi->get_user_info($owner_userno);
$ownerid = $owner_info->userid;
$ownerno = $owner_info->userno;

$symbol = $goods_idx;

$goods_info = $tradeapi->query_fetch_object("SELECT * FROM js_auction_goods WHERE idx='{$tradeapi->escape($goods_idx)}'");

if(!$goods_info->idx){
    $tradeapi->error('501', __('상품정보가 없습니다.'));
}

if ($goods_info->pack_info != 'Y') {
    $tradeapi->error('502', __('묶음 상품만 추가할 수 있습니다.'));
}

// 상품 이름 변경
$goods = $tradeapi->query_list_object("SELECT * FROM js_auction_goods WHERE pack_info = '{$goods_idx}'");
$goods_cnt = count($goods)+1;

foreach ($goods as $k => $v) {
    $count = null;
    $new_title = preg_replace('/\\[(\\d{1,})\\/(\\d{1,})\\]/', '[$1/'.$goods_cnt.']', $v->title,-1 ,$count);
    $tradeapi->query("update js_auction_goods set title='{$new_title}'  where idx='{$v->idx}'");
}

$sql = "";
$_goods_idx = $tradeapi->gen_id();
$now = date('Y-m-d H:i:s');
foreach ($goods_info as $k => $v) {
    if ($k == "idx") {
        $sql .= " idx = '{$_goods_idx}', ";
    } else if ($k == "stock_number") {
        $sql .= " stock_number = '{$stock_number}', ";
    } else if ($k == "title") {
        $count = null;
        $new_title = "[{$goods_cnt}/{$goods_cnt}] {$v}";

        $sql .= " title = '{$new_title}', ";
    } else if ($k == "pack_info") {
        $sql .= " pack_info = '{$goods_idx}', ";
    } else if ($k == "owner_userno") {
        $sql .= " owner_userno = '{$ownerno}', ";
    } else if ($k == "reg_date") {
        $sql .= " reg_date = '{$now}', ";
    } else if ($k == "nft_id") {
        $sql .= " nft_id = '{$_goods_idx}', ";
    } else if ($k == "mod_date") {
        $sql .= " mod_date = null, ";
    } else if ($k == "goods_grade") {
        $sql .= " goods_grade = '{$goods_grade}', ";
    } else {
        $sql .= " {$k} = '{$tradeapi->escape($v)}', " ;
    }
}

$sql = substr($sql, 0, -2);
$result = $tradeapi->query("INSERT INTO js_auction_goods SET ".$sql);

$result = $tradeapi->query("INSERT INTO js_auction_inventory SET userno='{$tradeapi->escape($ownerno)}', goods_idx='{$tradeapi->escape($_goods_idx)}', userid='{$tradeapi->escape($ownerid)}', buy_price='0', buy_auction_idx='', reg_date='{$now}' ");

$r = "select * from js_trade_currency where symbol = '{$symbol}' and exchange='KRW' ";

$currency = $tradeapi->get_currency($symbol);
if (count($currency)>0) {

    // 생성지갑주소 추출 및 확인
    /**
    $currency = $tradeapi->query_fetch_object("SELECT base_coin, auction_manager_userno FROM js_exchange_currency ec LEFT JOIN js_auction_currency ac ON ec.symbol=ac.symbol WHERE ec.symbol='{$tradeapi->escape($symbol)}' ");
    if(!$currency) {
        $tradeapi->write_log( "nft_symbol의 정보가 없습니다. nft_symbol: {$symbol}");
        $tradeapi->error('504', "nft_symbol의 정보가 없습니다.");
    }
    $manager_userno = $currency->auction_manager_userno;
    $manager_address = $tradeapi->query_one("SELECT address FROM js_exchange_wallet WHERE userno='{$tradeapi->escape($manager_userno)}' AND symbol='{$tradeapi->escape($symbol)}'");
    **/
    $manager_userno = $ownerno;
    $manager_address = $tradeapi->query_one("SELECT address FROM js_exchange_wallet WHERE userno='{$tradeapi->escape($manager_userno)}' AND symbol='{$_goods_idx}'");
    if(!$manager_address) {
        $address = $tradeapi->create_wallet($manager_userno, $_goods_idx);
        if(!$address) {
            $tradeapi->write_log( "manager_address가 없어 생성하려 했지만 실패했습니다. manager_address: {$manager_address}, manager_userno: {$manager_userno}, item->idx: {$goods_info->idx}");
            $tradeapi->error('505', "manager_address가 없어 생성하려 했지만 실패했습니다. manager_address: {$manager_address}, manager_userno: {$manager_userno}, item->idx: {$goods_info->idx}");
        }
        $tradeapi->save_wallet($manager_userno, $_goods_idx, $address, 0, $goods_grade);
        $base_coin_symbol = $currency->base_coin;
        if($base_coin_symbol) {
            $tradeapi->save_wallet($manager_userno, $base_coin_symbol, $address, 0, $goods_grade);
        }
    }

    $owner_address = $tradeapi->query_one("SELECT address FROM js_exchange_wallet WHERE userno='{$tradeapi->escape($ownerno)}' AND symbol='{$tradeapi->escape($_goods_idx)}'");

    $tradeapi->query("UPDATE js_exchange_wallet SET confirmed=confirmed+'{$tradeapi->escape($goods_info->nft_max_supply)}' WHERE userno='{$tradeapi->escape($ownerno)}' AND symbol='{$tradeapi->escape($_goods_idx)}' AND goods_grade='{$tradeapi->escape($goods_grade)}'");

    $tradeapi->query("INSERT INTO js_exchange_wallet_txn SET userno='{$tradeapi->escape($ownerno)}', symbol='NFTN',  address='{$tradeapi->escape($owner_address)}', regdate='{$now}', txndate='{$now}', address_relative='{$tradeapi->escape($manager_address)}', txn_type='MI', direction='I', nft_id='{$tradeapi->escape($_goods_idx)}', amount='1', fee='0', tax='0', status='D', service_name='AUCTION', key_relative='{$tradeapi->escape($goods_info->key_relative)}', txn_method='COIN', app_no='".__APP_NO__."', msg=''  ");

    // js_exchange_wallet_nft 에 저장?  - 민트 되지 않아서 지갑에는 넣지 않는다? 블록체인 데이터만 사용합니다.
    $tradeapi->query("INSERT INTO js_exchange_wallet_nft SET symbol='NFTN', tokenid='{$tradeapi->escape($_goods_idx)}', userno='{$tradeapi->escape($ownerno)}', amount='{$tradeapi->escape($goods_info->nft_max_supply)}', reg_date=NOW(), mode_date=NULL");
}

$tradeapi->success(array('goods_idx'=> $_goods_idx));