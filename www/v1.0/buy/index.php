<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

$tradeapi->set_logging(true);
$tradeapi->set_log_dir(__dir__.'/../../log/'.basename(__dir__).'/');
$tradeapi->set_log_name('');
$tradeapi->write_log("REQUEST: " . json_encode($_REQUEST));

// 로그인 세션 확인.
$tradeapi->checkLogin();
$userno = $tradeapi->get_login_userno();
$userno_buy = $userno;

// validate parameters
$symbol = checkSymbol(strtoupper(checkEmpty($_REQUEST['symbol'], 'symbol'))); // 코인
$exchange = checkSymbol(strtoupper(setDefault($_REQUEST['exchange'], $tradeapi->default_exchange))); // 구매 화폐
$price = checkQuotePrice(checkZero(checkNumber($_REQUEST['price']), 'price'), $exchange );// 가격
$volume = checkZero(checkNumber($_REQUEST['volume']), 'volume');// 구매량
$amount = setDefault($_REQUEST['amount'], ''); // 구매금액
$goods_grade = checkEmpty($_REQUEST['goods_grade'], 'goods_grade'); // 상품등급
$bool_agree = $_REQUEST['use_agreement']; //이용약관 동의여부

// --------------------------------------------------------------------------- //
// 매매 시간 확인 ( 9 ~ 18시 ) , 토요일(6)/일요일(7) 에는 매매 중지
// if(date('H') < 9 || 18 <= date('H') || date('N')=='6' || date('N')=='7' ) {
//     $tradeapi->error('100', '매매시간이 아닙니다. 평일 오전 9에서 오후 6시 사이에 매매해주세요.');
// }
// 매매 설정 확인
$config_basic = $tradeapi->get_config('js_config_basic');
/*if($config_basic->bool_trade!='1') {
    $tradeapi->error('100', '매매시간이 아닙니다. 평일 오전 9에서 오후 6시 사이에 매매해주세요.');
}*/

// 소숫점 사용 불가처리
// $d = $volume - floor($volume);
// if(0<$d && $d<1) {
//     // $tradeapi->error('000', __('Please enter only 4 decimal places for quantity.')); //수량에는 소수점 4자리까지만 입력해주세요.
//     $tradeapi->error('000', '구매수량에 소숫점을 입력하실수 없습니다.'); //수량에는 소수점 4자리까지만 입력해주세요.
// }

/***
 * where_confirm 계산 어디서 하는지 확인 변수
 * 
 */
$where_confirm = '';

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('master');

// 화폐 정보
$currency = ($tradeapi->get_currency($symbol))[0];
// 최소 거래량 확인.
if($currency->trade_min_volume>0 && $currency->trade_min_volume > $volume ) {
	$tradeapi->error('041',str_replace(array('{trade_min_volume}','{symbol}'), array($currency->trade_min_volume*1, $symbol), __('거래수량을 {trade_min_volume} {symbol}이상으로 입력해주세요.')));
}

// 현재가
$current_price = $tradeapi->get_spot_price($symbol, $exchange, $goods_grade);
$min_sell_price = $tradeapi->get_min_sell_price($symbol, $exchange, $goods_grade);
if(count($current_price)>0) {
    $current_price = $current_price[0];
    $current_price = $current_price->price_close;
} else { // 거래가 없는경우 현재가는 매도1호가로 설정.
    // 매도 1호가
    $current_price = $min_sell_price;
}
// 구매금액이 매도1호가보다 높은경우 매도1호가로 변경. - 주문오류로 더 높은 가격으로 구매하지 못하게 하기위함. - 미사용.
// if( $min_sell_price > 0 && $price > $min_sell_price ) {
//     $price = $min_sell_price;
// }
// 더 높은 가격으로 주문해도 주문한 가격까지 매도 주문을 처리합니다. 주문윗가격으로는 매수 안합니다.
// 시장가: 매수가 지정 없이 모든 매도 물량을 대상으로 수량만큼 구매합니다. 주문사량이 많은 경우 구매하지 못한 주문은 마지막 주문가에 쌓아둡니다.

// 매매 가격 범위 밖인지 확인.
$trade_price_info = (object) $tradeapi->get_trade_price_info($symbol, $exchange, $goods_grade);
if($trade_price_info->trade_max_price && $trade_price_info->trade_max_price < $price) {
    $tradeapi->error('101','매매 가격 범위('.number_format($trade_price_info->trade_min_price).' ~ '.number_format($trade_price_info->trade_max_price).')로 매매하실 수 있습니다.');
}
if($trade_price_info->trade_min_price && $trade_price_info->trade_min_price > $price) {
    $tradeapi->error('102','매매 가격 범위('.number_format($trade_price_info->trade_min_price).' ~ '.number_format($trade_price_info->trade_max_price).')로 매매하실 수 있습니다.');
}

//이용약관 확인
/*
if($bool_agree != "true") {
	$tradeapi->error('044', __('이용약관을 확인해 주세요.'));
}*/


// 지갑 - 구매금액을 확인해야 해서 $exchange 지갑을 가져옵니다.
$wallet_exchange = $tradeapi->get_wallet($userno_buy, $exchange);
// 잔액이 있어야 하기때문에 구지 지갑을 여기서 생성하지 않습니다. 없으면 잔액 부족으로 처리되도록 합니다.
// if(!$wallet_exchange) { // 종목용 지갑이 없을 수 있음 생성하기
//     $tradeapi->gen_wallet($userno_buy, $exchange);
//     $wallet_exchange = $tradeapi->get_wallet($userno_buy, $exchange);
// }
if ( count($wallet_exchange) > 0 ) {
    $wallet_exchange = $wallet_exchange[0];
}
// check locked
if($wallet_exchange && $wallet_exchange->locked != 'N') {
	$tradeapi->error('048', str_replace('{symbol}', $exchange, __('{symbol}지갑이 잠겨있어 매수하실 수 없습니다.')));
}
// if($wallet_exchange && $wallet_exchange->bool_buy=='0') {
// 	$tradeapi->error('048-1', str_replace('{symbol}', $exchange, __('{symbol}지갑이 잠겨있어 매수하실 수 없습니다.')));
// }

$wallet_symbol = $tradeapi->get_wallet($userno_buy, $symbol, $goods_grade);
if(!$wallet_symbol) { // 종목용 지갑이 없을 수 있음 생성하기
    $tradeapi->gen_wallet($userno_buy, $symbol, $goods_grade);
    $wallet_symbol = $tradeapi->get_wallet($userno_buy, $symbol, $goods_grade);
}
if ( count($wallet_symbol) > 0 ) {
    $wallet_symbol = $wallet_symbol[0];
}
// check locked
if($wallet_symbol->locked != 'N') {
	$tradeapi->error('048', str_replace('{symbol}', $symbol, __('{symbol}지갑이 잠겨있어 매수하실 수 없습니다.')));
}
// if($wallet_symbol->bool_buy=='0') {
// 	$tradeapi->error('048-1', str_replace('{symbol}', $symbol, __('{symbol}지갑이 잠겨있어 매수하실 수 없습니다.')));
// }

// 구매금액 -  주문가와 주문수량으로 구매금액을 계산하고 잔액을 확인만 함. 실제 구매금액은 거래 금액별로 다시 계산합니다.
$amount = $amount ? $amount : $price * $volume;
// 지불금액이 1원 밑이면 alert
if($amount<1) {
    $tradeapi->error('000', __('Your payment amount is too low. Please raise the quantity.')); // 지불금액이 너무 낮습니다. 수량을 올려주세요.
}
// 총 수수료
// 매도/매수 어떤거든 주문할때는 수수료가 선 차감하는것 없음. 매매가 발생할때 차감됨.
// 매수자는 수수료/세금 차감 없음.
// 매도자는 수수료/세금 차감함.
// $fee = $tradeapi->cal_fee($exchange, 'buy', $amount);
// 총 세금(보통 없지만 혹시 필요할때를 대비해서...)
// $tax = $tradeapi->cal_tax($exchange, 'buy', $amount);
// 총 구매금액
// $total_amount = $amount + $fee + $tax;
$total_amount = $amount;

// check balance
if($wallet_exchange->confirmed < $total_amount) {
    $tradeapi->error('016', __('There is not enough balance to buy.'));
}

// 수수료 계좌정보 조회
$user_fee = $tradeapi->get_member_info(2);// walletmanager 코인별로 분리해야 한다면 $currency->fee_save_userno 컬럼 추가해서 분리하기.
if(!$user_fee) {
    $tradeapi->error('017', __('There is no fee account information.'));
}
/*
$wallet_exchange_fee = $tradeapi->get_wallet($user_fee->userno, $exchange);
$wallet_exchange_fee = $wallet_exchange_fee ? $wallet_exchange_fee[0] : null;
if(!$wallet_exchange_fee) {
    $tradeapi->create_new_trade_wallet($user_fee->userno, $exchange);
    $wallet_exchange_fee = $tradeapi->get_wallet($user_fee->userno, $exchange);
    $wallet_exchange_fee = $wallet_exchange_fee ? $wallet_exchange_fee[0] : null;
}
*/

// transaction start
$tradeapi->transaction_start();

try {

    // 주문 목록에 구매내역 등록
    $address_buy = $wallet_symbol->address; // krw 계좌는 저장할 필요 없음.

    $r = $tradeapi->write_buy_order($userno_buy, $address_buy, $symbol, $exchange, $price, $volume, $total_amount, $goods_grade);
    $orderid_buy = $tradeapi->_recently_query['last_insert_id'];

    $trade_price = 0; // 최종 거래된 가격을 반영하기 위한 변수
    $trade_volume = 0; // 최종 거래된 수량을 반영하기 위한 변수
    $remain_volume_buy = $volume; // 남은 매수 수량
    $avg_trade_price = array();

    // 주문 목록에서 매수가 이하의 판매주문이 있는지 확인하고
    $orders_sell = $tradeapi->get_order_by_price('S', $symbol, $exchange, $price,'',$goods_grade);
    if(!$orders_sell && count($orders_sell)<1) {
        // 매도 주문이 없으니 주문금액 전액을 지갑에서 비용 차감. 즉, USD 차감.
        // 매도 주문이 없으니 바로 주문 등록됨 주문 등록으로 비용 차감되니까 실금액에서 차감하지 않음 (동결금액 확인 중요!)
        //$tradeapi->charge_buy_price($userno_buy, $exchange, $total_amount);
        
    } else {
        // 매도 주문이 있을때는 아래에서 각각 매매하면서 해당금액으로 KRW를 차감합니다.
        foreach($orders_sell as $order_sell) {

            if ($order_sell->goods_grade != $goods_grade) {
                continue;
            }

            if(empty($order_sell->orderid) || $order_sell->volume_remain <= 0 ) {
                continue;
            }
            // 구매량과 판매량을 비교해서 판매내역 수정.
            $orderid_sell = $order_sell->orderid; // 매도주문아이디
            $remain_volume_sell = $order_sell->volume_remain; // 남은 매도주문량
            if($remain_volume_sell <= $remain_volume_buy) {
                $trade_volume = $remain_volume_sell;
                $trade_status_sell = 'C'; // 판매물량을 전부 소진하니 판매상태를 완료 처리.
            } else { //if ($remain_volume_sell > $remain_volume_buy) {
                $trade_volume = $remain_volume_buy;
                $trade_status_sell = 'T'; // 판매물량이 남았으니 판매상태를 거래중 처리.
            }

            // 거래가격 = 실제로 최종 거래된 가격
            $trade_price = $order_sell->price ;
            $avg_trade_price[] = $trade_price * $trade_volume; // 각 금액별로 거래가격 * 거래량을 저장해 두었다가 평균을 냅니다.

            // 거래대금. = 가격*수량 매매 건별 가격으로 수수료 처리하기 위해 여기로 이동.
            $trade_amount = $trade_volume * $trade_price;

            // 구매자 지갑에서 USD 차감.
            $tradeapi->charge_buy_price($userno_buy, $exchange, $trade_amount);
            $where_confirm = $where_confirm.'2'.$userno_buy.'/'. $exchange.'/'. $trade_amount.'/';
            // 판매자 지갑에 돈 지불.
            $userno_sell = $order_sell->userno;
            // 거래 수수료
            $fee = $tradeapi->cal_fee($exchange, 'sell', $trade_amount);
            // 거래 세금(보통 없지만 혹시 필요할때를 대비해서...)
            $tax_transaction = $tradeapi->cal_tax($exchange, 'sell', $trade_amount);
            // 양도 소득세
            // 미 판매 수량의 평균 매수가를 구해야 함.
            $tax_income = 0 ; //$tradeapi->cal_tax($exchange, 'buy', $trade_amount);
            // 판매자 거래대금. = 거래대금 - 거래 수수료 - 거래 세금 - 양도 소득 세금.
            $trade_receive = $trade_amount - $fee - $tax_transaction - $tax_income;
            // 원단위 절삭.
            $trade_receive = floor($trade_receive); // floor($trade_receive*1)/1;
            // 판매 대금 지급
            $tradeapi->add_wallet($userno_sell, $exchange, $trade_receive);
            // 수수료 계좌에 수수료 지급.
            if($fee>0) {
                $tradeapi->add_wallet($user_fee->userno, $exchange, $fee);
                // $tradeapi->add_wallet_txn($user_fee->userno, $wallet_exchange_fee->address, $exchange, $userno_sell, 'R', $fee, 0, 0, "D", $orderid_buy, date('Y-m-d H:i:s'));
            }
            if($tax_transaction>0) {
                $tradeapi->add_wallet($user_fee->userno, $exchange, $tax_transaction);
                // $tradeapi->add_wallet_txn($user_fee->userno, $wallet_exchange_fee->address, $exchange, $userno_sell, 'R', $tax_transaction, 0, 0, "D", $orderid_buy, date('Y-m-d H:i:s'));
            }
            if($tax_income>0) {
                $tradeapi->add_wallet($user_fee->userno, $exchange, $tax_income);
                // $tradeapi->add_wallet_txn($user_fee->userno, $wallet_exchange_fee->address, $exchange, $userno_sell, 'R', $tax_income, 0, 0, "D", $orderid_buy, date('Y-m-d H:i:s'));
            }

            // 판매 주문 수정.
            $tradeapi->trade_order($orderid_sell, $symbol, $exchange, $trade_volume, $trade_status_sell);

            // 구매자 지갑에 코인 지불
            $tradeapi->add_wallet($userno_buy, $symbol, $trade_volume, $goods_grade);

            // 남은 구매량
            $remain_volume_buy = $remain_volume_buy > $remain_volume_sell ? $remain_volume_buy - $remain_volume_sell : 0;
            // 구매 주문 수정.
            if( $remain_volume_buy > 0 ) {
                $trade_status_buy = 'T';
            } else {
                $trade_status_buy = 'C';
            }
            $tradeapi->trade_order($orderid_buy, $symbol, $exchange, $trade_volume, $trade_status_buy);

            // 가격 상승/하락/보합
            $price_updown = $trade_price > $current_price ? 'U' : ($trade_price < $current_price ? 'D' : '-') ;

            // 거래 내역 저장.
            $tradeapi->write_trade_txn($symbol, $exchange, $trade_price, $trade_volume, $orderid_buy, $orderid_sell, $fee, $tax_transaction, $tax_income, $price_updown, $goods_grade);
            $txnid = $tradeapi->_recently_query['last_insert_id'];
            // 거래 내역 인댁스 저장. 쩝.
            $tradeapi->write_trade_ordertxn($symbol, $exchange, $userno_sell, $orderid_sell, $txnid, $goods_grade);
            $tradeapi->write_trade_ordertxn($symbol, $exchange, $userno_buy, $orderid_buy, $txnid, $goods_grade);

            // 호가 데이터 갱신 - 거래가에 호가 갱신.
            $tradeapi->set_quote_data($symbol, $exchange, $trade_price, $goods_grade);

            $goods_info = $tradeapi->query_fetch_object("SELECT g.idx goods_idx, g.*  FROM js_auction_goods g WHERE idx='{$symbol}' ");
			
			$sql2 = "SELECT COUNT(*) AS cnt, SUM(price) AS t_price FROM js_auction_goods WHERE owner_userno = '{$userno_buy}' AND pack_info = '{$symbol}';";
	
			$before_buy = $tradeapi->query_list_object($sql2);
			
			//매입가격 재계산 및 데이터 업데이트
			$before_buy_cnt = 0;//이전의 구입 
			$before_buy_total = 0;//이전의 구입 가격 총액
			$buy_total = ($trade_price * $trade_volume);
			if(!empty($before_buy[0]->t_price)){
				$before_buy_cnt = $before_buy[0]->cnt*1;
				$before_buy_total = $before_buy[0]->t_price;
			}
			
			$total_price = $buy_total + $before_buy_total;
			$total_cnt = $before_buy_cnt + $trade_volume;
			$buy_avg_price = $total_price/$total_cnt;
			
            // pack일 경우
            if ($goods_info->pack_info=='Y') {
				/*230204 mk 기존 소스 변경
                $sql = "SELECT * FROM js_auction_goods WHERE pack_info='{$goods_info->idx}' and owner_userno='{$user_fee->userno}' limit  {$volume} ";
                $goods_remain_list =  $tradeapi->query_list_object($sql);

                if (count($goods_remain_list) < $volume) {
                    $tradeapi->error('049', __('Please enter the order quantity below the remain quantity.')); //주문수량을 잔여수량 이하로 입력해주세요.
                }

                $user_buy_info = $tradeapi->get_member_info($userno_buy);

                foreach ($goods_remain_list as $good) {
                    
					// js_auction_goods.owner_userno 를 판매자에서 구매자 회원번호로 변경 수량만큼
                    $tradeapi->query("UPDATE js_auction_goods SET owner_userno={$user_buy_info->userno} WHERE idx='{$good->idx}' ");
                    // js_auction_inventory에 goods_idx 별로 회원정보 변경(또는 추가)
                    $now_date = date('Y-m-d H:i:s');
                    $tradeapi->query("INSERT INTO js_auction_inventory (goods_idx, userno, userid, amount, buy_price, buy_auction_idx, reg_date) 
                                        VALUES ('{$good->idx}', {$user_fee->userno}, '{$user_buy_info->userid}', 1, 0, '', '{$now_date}') 
                                        on duplicate key update userno={$user_buy_info->userno} , userid='{$user_buy_info->userid}'");
					
                }*/
				//IDX값 모두 가져오기
				$t_volume = $trade_volume *1;
				$sql = "SELECT * FROM js_auction_goods WHERE pack_info='{$goods_info->idx}' and owner_userno='{$userno_sell}' limit  {$t_volume} ";
				
				$goods_remain_list =  $tradeapi->query_list_object($sql);
						
				foreach ($goods_remain_list as $good) {
				//업데이트 - UPDATE 쿼리 -IDX 값을 찾아야함
					$sql = "UPDATE js_auction_goods SET ";
					$sql .= "owner_userno = '{$userno_buy}', ";
					$sql .= "price = '{$buy_avg_price}', ";
                    $sql .= "mod_date = NOW() ";
					$sql .= "WHERE IDX='{$good->idx}' ";
					$sql .= "AND owner_userno='{$userno_sell}' ;";
								
					$tradeapi->query_fetch_object($sql);
					
					//auction_goods_history에 내용 추가
					$sql2 = "INSERT INTO kkikda.js_auction_goods_history(idx, active, stock_number, pack_info, seller_userno, owner_userno,  nft_link, exchange_info, price)";
					$sql2 .= "VALUES('{$good->idx}', 'Y', '{$good->stock_number}', '{$symbol}', '{$userno_sell}', '{$userno_buy}', '', '1', '{$trade_price}');";
					
					$tradeapi->query_fetch_object($sql2);
				}

				$sql = "UPDATE js_auction_goods SET ";
				$sql .= "price = '{$buy_avg_price}' ";
				$sql .= "WHERE owner_userno='{$userno_buy}' ";
				$sql .= "AND pack_info='{$goods_info->idx}' ;";		

				$tradeapi->query_fetch_object($sql);
            }
			

            // 남은구매량이 없으면 종료
            if( $remain_volume_buy <= 0 ) {
                break;
            }
        }

        // 매도 주문들 처리 하고도 남은구매량이 있으면 구매자의 USD 차감함.
        if( $remain_volume_buy > 0 ) {
            $remain_amount = $remain_volume_buy * $price; // 남은 주문수량 * 주문가 = 남은매수금액;
            $tradeapi->charge_buy_price($userno_buy, $exchange, $remain_amount);
            $where_confirm = $where_confirm.'4'.$tradeapi->charge_buy_price_sql($userno_buy, $exchange, $remain_amount).'/';
        }


    }

    // 호가 데이터 갱신 - 주문가에 주문량 남아 있을수 있어서 호가 갱신함.
    $tradeapi->set_quote_data($symbol, $exchange, $price, $goods_grade);

    // @todo 현제가 거래 - 그냥 현제가로 매수할때


    // 구매 거래 완료시 구매내역 업데이트
    // if($remain_volume_buy < $volume) {
    //     if($remain_volume_buy <= 0 ) {
    //         $trade_status = 'C'; // 완료 처리.
    //         $remain_volume_buy = 0; // 남은 물량 0으로 설정.
    //     } else {
    //         $trade_status = 'T'; // 판매물량이 남았으니 거래중 처리.
    //     }
    //     $r = $tradeapi->trade_order($orderid_buy, $symbol, $exchange, $remain_volume_buy, $trade_status);
    // }

    // 성공시 commit
    $tradeapi->transaction_end('commit');

    if($trade_price>0) {
        // 현재가 갱신
        $tradeapi->set_current_price_data($symbol, $exchange, $goods_grade);
        $where_confirm = $where_confirm.'5 '.$exchange.'/';
    }

    // 알림
    if (!$trade_status_buy) {
        $tradeapi->put_message(2, $userno_buy,"[{$currency->name} ({$goods_grade})] {$amount} ({$volume}개) KRW 구매 대기 되었습니다.");
    } else if ($trade_status_buy == "C") {
        $tradeapi->put_message(2, $userno_buy,"[{$currency->name} ({$goods_grade})] {$amount} ({$volume}개) KRW 구매 완료 되었습니다.");
    }

} catch(Exception $e) {

    // 실패시 rollback
    $tradeapi->transaction_end('rollback');
    $tradeapi->error('005', __('A system error has occurred.'));

}
// transaction end

$tradeapi->gen_chanrt_data ($symbol, $exchange, $goods_grade);

// 평균 거래금액
$avg_trade_price = count($avg_trade_price) > 0 ? round( array_sum($avg_trade_price) / ($volume-$remain_volume_buy), 4 ) : 0;
$remain_volume_buy = round($remain_volume_buy, 4);
 
// gen return value
$r = array('price'=>$avg_trade_price, 'volume'=>round($volume-$remain_volume_buy,4), 'amount'=>round($avg_trade_price*($volume-$remain_volume_buy),4)*1, 'order_price'=>$price, 
'remain_volume'=>$remain_volume_buy, 'orderid'=>$orderid_buy, 'where_money'=>$where_confirm);

// response
$tradeapi->success($r);
