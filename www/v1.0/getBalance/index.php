<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
$tradeapi->checkLogin();
$userno = $tradeapi->get_login_userno();

// validate parameters
$symbol = checkSymbol(strtoupper(setDefault($_REQUEST['symbol'], 'ALL')));
$exchange = checkSymbol(strtoupper(setDefault($_REQUEST['exchange'], $tradeapi->default_exchange)));

// --------------------------------------------------------------------------- //

// currency 정보
$currencies = array();

// 슬레이브 디비 사용하도록 설정.
$tradeapi->set_db_link('slave');

// 전체 조회시.
if($symbol=='ALL') {
    $symbol = '';
}

// airdrop 확인 - 아직 사용할 수는 없지만 보유하고 있는 수량으로 계산에 추가합니다.
// $airdrops = $tradeapi->query_list_object_column("SELECT symbol, SUM(volumn) airdrop FROM js_trade_airdrop WHERE userno='{$tradeapi->escape($userno)}' AND paydate IS NULL GROUP BY symbol ", 'symbol');
// foreach($airdrops as $s => $row) {
//     // 지갑에 없으면 해당 심볼로 지갑을 만들어 줍니다.
//     $wallet = $tradeapi->query_one("SELECT COUNT(*) FROM js_exchange_wallet WHERE userno='{$tradeapi->escape($userno)}' AND symbol='{$tradeapi->escape($row->symbol)}' ");
//     if(!$wallet) {
//         $tradeapi->gen_wallet($userno, $row->symbol);
//     }
// }

// check wallet owner
$wallets = $tradeapi->get_wallet($userno, $symbol);
if(!$wallets) {
    $wallets = array();
}

// 은행정보
$query = "select bank_name, account_no, account_user, concat(bank_name,' / ', account_no,' / ', account_user) bank_full_info  from js_config_account  WHERE coin='KRW' ";
$bank_info = $tradeapi->query_fetch_object($query);

// krw 매수 대기중 금액.
$_amount_exchange = 0;

//총보유금액
//다국어시 이거 무조건 고쳐야함(한화고정)
$query_tot = "SELECT confirmed FROM js_exchange_wallet WHERE userno = '".$userno."' and symbol = 'KRW' AND goods_grade = '' ;";
$total_money = $tradeapi->query_fetch_object($query_tot);	


//주문금액 확인을 위한 array 생성
$query_symbol = "SELECT DISTINCT pack_info FROM js_auction_goods WHERE pack_info != 'Y';";
$symbol_name = $tradeapi->query_list_object($query_symbol);	

$list_table = array();
for($i=0; $i<count($symbol_name); $i++) {
	$from = "js_trade_".strtolower($symbol_name[$i]->pack_info)."".strtolower($tradeapi->default_exchange)."_order";
    
	$sql2 = "SELECT IFNULL(SUM(price*volume_remain),'0') remain FROM {$from} WHERE `status` IN ('O','T') AND trading_type='B' AND userno='{$tradeapi->escape($userno)}' ";
	
	//$wait_buy += $tradeapi->query_fetch_object($sql2);
	
	$wait_buy += $tradeapi->query_one($sql2);
}

$total_money = $total_money->confirmed - $wait_buy;
//출금예정
$sql = "SELECT IFNULL(SUM(amount+fee),0) amount FROM js_exchange_wallet_txn WHERE direction = 'O' AND status = 'O' AND userno='{$tradeapi->escape($userno)}'; ";

$withdrawing = $tradeapi->query_one($sql);


// 잔액 만큼의 구매 정보 추출
for($i=0; $i<count($wallets); $i++) {
    $wallet = (object) $wallets[$i];

    // USD 입금주소 설정 - 중국어에서는 중국은행이 나오도록
    if($wallet->symbol=='KRW') {
        $wallet->address = $bank_info->bank_full_info; // 한국어 은행.
        // if($tradeapi->get_i18n_lang()=='zh') {
        //     $wallet->address = '농협 / 355-0064-6186-53 / (주)패션아라'; // 중국어 은행.
        // }
    }
	$wallet->total_money = $total_money;

    $currency = null;
    if(isset($currencies[$wallet->symbol])) {
        $currency = $currencies[$wallet->symbol];
    } else {
        $currency = $tradeapi->get_currency($wallet->symbol);
        if($currency) {
            $currency = $currency[0];
            $currencies[$wallet->symbol] = $currency;
        }
    }

    // 소숫접 자릿수
    if($currency) {
        $d = $currency->display_decimals;
    } else {
        $d = 4;
    }
    $wallet->display_decimals = $d*1;
    $wallet->currency_price = $currencies[$wallet->symbol]->price * 1;

    // 매매 가능 금액
    $wallet->tradable = $wallet->confirmed * 1;

    // 1일 출금 가능금액
    $wallet->tradable_today = $currency->out_max_volume_1day < $wallet->tradable ? $currency->out_max_volume_1day : $wallet->tradable;

    // 출금 중 금액
    //$wallet->withdrawing = $tradeapi->query_one("SELECT IFNULL(SUM(amount+fee),0) amount FROM js_exchange_wallet_txn WHERE symbol='{$tradeapi->escape($wallet->symbol)}' AND `status` = 'O' AND txn_type='W' AND userno='{$tradeapi->escape($userno)}'") * 1;
    //$_amount_exchange += $wallet->withdrawing;
	$wallet->withdrawing = $withdrawing*1;
    // 잠긴 금액
    $wallet->locked = $airdrops[$wallet->symbol]->airdrop * 1;
	

    $order_table = "js_trade_".strtolower($wallet->symbol)."".strtolower($tradeapi->default_exchange)."_order";
    $order_table_exists = $tradeapi->isTable($order_table);
    // 매도 중 금액
    $sql = "SELECT SUM(volume_remain) remain FROM {$order_table} WHERE `status` IN ('O','T') AND trading_type='S' AND userno='{$tradeapi->escape($userno)}'";
    if($wallet->goods_grade) {
        $sql.= " AND goods_grade='{$tradeapi->escape($wallet->goods_grade)}'";
    }
    $wallet->trading = $order_table_exists ? $tradeapi->query_one($sql) * 1 : 0;
    // if($wallet->goods_grade) {
    //     var_dump($order_table, $order_table_exists, $wallet->goods_grade, $sql, $wallet->trading);exit;
    // }
    //230202 매수 중 금액이라 써잇는데 결과값은 항상 0; 혹시몰라 남겨두지만 필요할 가능성이 낮음 -> null갑으로 인한 오류
    $_amount_exchange += $order_table_exists ? $tradeapi->query_one("SELECT SUM(price*volume_remain) remain FROM {$order_table} WHERE status IN ('O','T') AND trading_type='B' AND userno='{$tradeapi->escape($userno)}'") : 0;
	
	// (new)매수 중 금액 -> 쿼리실행시 오류(table이름에 대한 오류로 해결못했음)
	//$wait_buy = count($symbol_name).'/'.$symbol.'/'.in_array($symbol,$symbol_name);
	
	/*if(in_array($symbol,$list_table)){
		$sql2 = "SELECT IFNULL(SUM(price*volume_remain),'0') remain FROM {$order_table} WHERE `status` IN ('O','T') AND trading_type='B' AND userno='{$tradeapi->escape($userno)}' ";
	
		//$sql2 = "SELECT count(*) FROM {$order_table} WHERE status IN ('O','T') AND trading_type='B' AND userno='{$tradeapi->escape($userno)}' ";
		$text = $symbol.'/'.in_array($symbol,$list_table).'/'.count($list_table).'/'.$list_table[0].";";
		//쿼리 실행시 오류...
		//$wait_buy = $tradeapi->query_fetch_object($sql2);
		$wait_buy = '1111111111111111111111';
		
		//$wallet->wait_buy = $tradeapi->calc_wait_buy($order_table, $userno);
		//$wallet->wait_buy = $tradeapi->query_fetch_object($sql2);
	}*/
	$wallet->wait_buy = $wait_buy;
	
    // 전체 잔액
    $wallet->valuation = (($wallet->tradable + $wallet->trading + $wallet->locked).'')*1;

    // 잔액에서 외부에서 입금한 수량 제외하기. 순수 거래에 대한 순익만 계산하기 위함.
    // db에서 가져와야 함.
    $wallet->deposit_volume = $tradeapi->get_receive_volume($wallet->symbol, $userno);

    // 평가 금액
    $wallet->eval_amount = $wallet->currency_price * ($wallet->confirmed - $deposit_volume);

    $wallets[$i] = $wallet;

    // 거래하는 종목이 아니면 평균매수가 ... 등등 정보 구하지 않는다.
    if($currencies[$wallet->symbol]->tradable!='Y') { 
        continue;
    }

    // 구매에 의한 잔액
    $txn_buy_volume = $wallet->confirmed * 1;
    $txn_buy_volume -= $wallet->deposit_volume;
    $wallet->txn_buy_volume = $txn_buy_volume;
	$sum_buy_goods = 0;
	$wallet->sum_buy_goods = $sum_buy_goods;
    // 총 구매 금액(구매에 의한 잔액에 해당하는 것만 계산)
    $sum_buy_amount = 0;
    $t=0;
    // $wallet->txns = array();
    while( $txn_buy_volume > 0 ) {
        $txns = $tradeapi->get_buy_ordertxn($wallet->symbol, $exchange, $userno);
		
        if($txns) {
            foreach($txns as $txn) {
                $buy_volume = $txn_buy_volume <= $txn->volume ? $txn_buy_volume : $txn->volume * 1;
                $sum_buy_amount += $txn->price * $buy_volume;
                $txn_buy_volume -= $txn->volume;
                // $wallet->txns[] = array('volume'=>$buy_volume*1, 'price'=>$txn->price*1);
                if($txn_buy_volume <= 0) {
                    break;
                }
            }
        } else {
            break;
        }
		
		

        if($t>100) { // 무한루프 방지.
            // var_dump($wallet->symbol, $exchange, $userno, $t);
            break;
        }
	
        $t++;
    }
	$txns2 = $tradeapi->get_buy_ordertxn2($wallet->symbol, $userno);
	
	if($txns2){
		$sum_buy_goods = $txns2[0]->price;
	}else{
		$sum_buy_goods = $txn_buy_volume;
	}
	$wallet->sum_buy_goods = $sum_buy_goods;
	
	

    // 평균 매수가
    $wallet->avg_buy_price = $wallet->confirmed>0 ? round($sum_buy_amount / $wallet->confirmed, $d) : 0;
    // 매수 금액
    $wallet->sum_buy_amount = round($sum_buy_amount, $d);
    // 평가 수익
    $wallet->eval_income = round($wallet->eval_amount - $wallet->sum_buy_amount, $d);
    // 평가 수익률
    $wallet->eval_income_rate = $wallet->sum_buy_amount>0 ? round($wallet->eval_income/$wallet->sum_buy_amount, 2) : 0;
    // 생산년도
    $sql_make_year = "SELECT meta_val FROM js_auction_goods_meta WHERE goods_idx = '$wallet->symbol' AND meta_key = 'meta_wp_production_date'";

    $make_year = $tradeapi->query_one($sql_make_year);
    $wallet->make_year = $make_year;

    $wallets[$i] = $wallet;
}
	
// 교환 화폐의 잔액 처리.
for($i=0; $i<count($wallets); $i++) {
    $wallet = $wallets[$i];
    if($wallet->symbol==$tradeapi->default_exchange) {
        $wallet->name = $wallet->name;
        $wallet->icon_url = $wallet->icon_url;
        $wallet->trading = $_amount_exchange;
        $wallet->tradable = $wallet->confirmed;
        $wallet->valuation = $wallet->tradable + $wallet->trading;
        $wallets[$i] = $wallet;
        break;
    }	
}
// var_dump($wallets, $tradeapi->default_exchange); exit;


// if (! $wallet->address) {
    // $tradeapi->error('013', __('Wallet address is missing. Please create an address.'));
// }

// response
// var_dump($wallets); exit;
$tradeapi->success($wallets);
