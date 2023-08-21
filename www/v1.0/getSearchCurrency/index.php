<?php
/**
 * 지갑에서 사용하는 모든 화폐정보 표시 API
 */
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
// $tradeapi->checkLogin();
$userno = $tradeapi->get_login_userno();

// validate parameters
//$symbol = checkSymbol(strtoupper(setDefault($_REQUEST['symbol'], 'ALL')));
$symbol = setDefault($_REQUEST['symbol'], '');
$name = setDefault($_REQUEST['name'], '');
$cal_base_price = setDefault($_REQUEST['cal_base_price'], '');
$getNFTData = setDefault($_REQUEST['getNFTData'], 'Y');

// --------------------------------------------------------------------------- //

// 슬레이브 디비 사용하도록 설정.
$tradeapi->set_db_link('slave');

$symbol = $tradeapi->query_list_one("SELECT idx FROM js_auction_goods WHERE pack_info = 'Y' AND title LIKE '%{$item_name}%';"); 
if(!$symbol) {
    $symbol = array('없는심볼');
}

// var_dump($symbol); exit;

// check wallet owner
// $currency = $tradeapi->get_currency($symbol);
$tradeapi->set_cache_dir(__SRF_DIR__.'/cache/getCurrency/');
$cache_id = sha1('getCurrency-'.print_r($symbol, true).'-'.$name);
$cachetime = 60;
$c = $tradeapi->get_cache($cache_id, $cachetime);
// if($c=='') {
    $c = $tradeapi->set_cache($cache_id, $tradeapi->get_currency($symbol, '', $name), $cachetime); // js_trade_currency
// }
$tradeapi->clear_old_file($cachetime);

if($c && $cal_base_price=='Y') {
    
// SELECT 'eth' symbol, SUM(volume * price) / SUM(volume) prev_avg_price FROM `js_trade_ethkrw_txn` FORCE INDEX(time_traded) WHERE time_traded LIKE CONCAT((SELECT DATE(MAX(time_traded)) FROM `js_trade_ethkrw_order` WHERE `status` IN ('T', 'C')),'%')
// UNION ALL
// SELECT 'btc' symbol, SUM(volume * price) / SUM(volume) prev_avg_price FROM `js_trade_btckrw_txn` FORCE INDEX(time_traded) 
// WHERE time_traded LIKE CONCAT((SELECT DATE(MAX(time_traded)) FROM `js_trade_btckrw_order` WHERE `status` IN ('T', 'C')),'%')

    $sql = array();
    foreach($c as $row) {
        if($row->tradable!='Y' || !$row->symbol) continue; // 매매가능 종목만 계산합니다.
        $symbol = strtolower($row->symbol);
        $SYMBOL = strtoupper($row->symbol);
        $exchange = strtolower($tradeapi->default_exchange);
        $sql[] = "SELECT '{$SYMBOL}' symbol, ROUND(SUM(volume * price) / SUM(volume)) prev_avg_price FROM `js_trade_{$symbol}{$exchange}_txn` FORCE INDEX(time_traded) WHERE time_traded LIKE CONCAT((SELECT DATE(MAX(time_traded)) FROM `js_trade_{$symbol}{$exchange}_txn` FORCE INDEX(time_traded)  WHERE time_traded < DATE(NOW())),'%')";
    }
    $base_prices = $tradeapi->query_list_object(implode(' UNION ALL ', $sql));
    for($i=0; $i<count($c); $i++) {
        $symbol = $c[$i]->symbol;
        foreach($base_prices as $p) {
            if($symbol == $p->symbol) {
                $c[$i]->base_price = $p->prev_avg_price;
                break;
            }
        }
    }
}

// NFT 정보 추가
if($getNFTData=='Y') {
    foreach($c as $i => $currency) {
        $currency = (array) $currency;

        // NFT 상품 정보 추가
        $_nft_info = (array) $tradeapi->db_get_row('js_auction_goods', array('idx'=>$currency['symbol']));
        $currency = array_merge($_nft_info,  $currency);
        
        // // 메타데이터 정보 추가
        $meta_data = $tradeapi->db_get_list('js_auction_goods_meta', array('goods_idx'=>$currency['symbol']));
        foreach($meta_data as $row) {
            $currency[$row->meta_key] = $row->meta_val;
        }
        // // 인증마크 정보 추가
        if($currency['meta_certification_mark']) {
            $_cm_info = $tradeapi->db_get_row('js_auction_certification_marks', array('idx'=>$currency['meta_certification_mark']));
            $currency['meta_certification_mark_name'] = $_cm_info->title ?? '';
            $currency['meta_certification_mark_image'] = $_cm_info->image_url ?? '';
        }

        $c[$i] = $currency;
    }
}

// 좋아요 정보 추가
if($userno) {
    foreach($c as $i => $currency) {
        $currency = (array) $currency;

        // NFT 상품 정보 추가
        $_subscribe_info = $tradeapi->db_get_row('js_subscribe', array('target_idx'=>$currency['symbol'], 'target_type'=>'trade', 'subscriber_userno'=>$userno));
        $currency['like'] = $_subscribe_info->like=='Y' ? 'Y' : 'N';
        $currency['subscribe'] = $_subscribe_info->subscribe=='Y' ? 'Y' : 'N';
        $currency['notification'] = $_subscribe_info->notification=='Y' ? 'Y' : 'N';

        $c[$i] = $currency;
    }
}


// response
$tradeapi->success($c);
