<?php
/**
 * 상품반출 api입니다
 * 
 * userno 기반입니다.
 * js_takeout_item에 내용을 저장하고 지갑내 수량 감소(js_exchage_wallet cofirmed -> -1 ,js_exchage_wallet_nft -> amount=0), 
 * 전체 재고(js_auction_goods active ->N/ owner_userno = 1005 ,js_auction_inventory -> amount=0) 내 내용 삭제(삭제 DB로 이동), 
 * 히스토리 기록(js_auction_goods_history -> exchange_info = 2, owner_userno = 1015)
 * 
 */
include dirname(__file__) . "/../../lib/TradeApi.php";

$tradeapi->set_logging(true);
$tradeapi->set_log_dir(__dir__.'/../../log/'.basename(__dir__).'/');
$tradeapi->set_log_name('');
$tradeapi->write_log("REQUEST: " . json_encode($_REQUEST));

// 로그인 세션 확인.
$tradeapi->checkLogin();
$userno = $tradeapi->get_login_userno();

// POST 요청으로부터 데이터 배열 받기
if (isset($_POST['dataArray'])) {
    // 전달받은 데이터 배열 저장
    $dataArray = $_POST['dataArray'];

    // 이제 PHP에서 $dataArray를 원하는 방식으로 처리할 수 있습니다.
    $text = '';
    // 예를 들어, 각 데이터 항목에 접근하여 처리하는 방법:
    foreach ($dataArray as $data) {
        $name = $data['name'];
        $symbol = $data['symbol'];
        $cnt = $data['cnt'];
        $production_dat = '';
        $item_idx = '';

        $p_date_sql = "SELECT meta_val FROM js_auction_goods_meta WHERE goods_idx = '{$symbol}' AND meta_key = 'meta_wp_production_date';";
        $p_date = $tradeapi->query_one($p_date_sql);

        //메인반출내용 입력
        $insert_sql = "INSERT INTO kkikda.js_takeout_item
        (takeout_userno, takeout_item_name, takeout_item_count, takeout_item_pack_info, takeout_item_idx, takeout_item_production_date, takeout_state, takeout_request_date, takeout_complete_date, takeout_note1, takeout_note2, takeout_note3, takeout_note4, takeout_note5)
        VALUES('{$userno}','{$name}', '{$cnt}', '{$symbol}', 'main', '{$p_date}', 'R', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, '', '', '', '', '');";
        $tradeapi->query_one($insert_sql);

        $item_idx_sql = "SELECT idx FROM js_auction_goods WHERE owner_userno = '{$userno}' and pack_info = '{$symbol}' LIMIT {$cnt};";
        $idx_array = $tradeapi->query_list_object($item_idx_sql);
        //세부 idx입력
        foreach ($idx_array as $data) {
            $insert_sql = "INSERT INTO kkikda.js_takeout_item
            (takeout_userno, takeout_item_name, takeout_item_count, takeout_item_pack_info, takeout_item_idx, takeout_item_production_date, takeout_state, takeout_request_date, takeout_complete_date, takeout_note1, takeout_note2, takeout_note3, takeout_note4, takeout_note5)
            VALUES('{$userno}','{$name}', '{$cnt}', '{$symbol}', '{$data->idx}', '{$p_date}', 'I', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 'idx info 내용입니다.', '', '', '', '');";
            $tradeapi->query_one($insert_sql);

            
            /**
 * 상품반출 api입니다
 * 
 * userno 기반입니다.
 * js_takeout_item에 내용을 저장하고 지갑내 수량 감소(js_exchange_wallet cofirmed -> -1 ,js_exchange_wallet_nft -> amount=0), 
 * 
 * 전체 재고(js_auction_goods active ->N/ owner_userno = 1005 , js_auction_inventory -> amount=0) 내 내용 삭제(삭제 DB로 이동), 
 * 히스토리 기록(js_auction_goods_history -> exchange_info = 2, owner_userno = 1005)
 * {$data->idx}
 * '{$userno}'
 */
            
            //exchage_wallet
            $update_sql_exchage_wallet = "UPDATE `kkikda`.`js_exchange_wallet` SET `confirmed`= `confirmed`-1, regdate=NOW() WHERE  `userno`='{$userno}' AND `symbol`='{$data->idx}';";
            $tradeapi->query_one($update_sql_exchage_wallet);

            $update_sql_exchange_wallet_nft = "UPDATE `kkikda`.`js_exchange_wallet_nft` SET `amount`='1', reg_date=NOW() WHERE  `symbol`='GJ2GW26TZH' AND `tokenid`='GJ2GY95KNN';";
            $tradeapi->query_one($update_sql_exchange_wallet_nft);
            
            //auction_goods
            $update_sql_auction_goods = "UPDATE `kkikda`.`js_auction_goods` SET `active`='N', owner_userno = '1015', reg_date=NOW() WHERE  `idx`='{$data->idx}' AND `owner_userno` = '{$userno}';";
            $tradeapi->query_one($update_sql_auction_goods);

            $update_sql_auction_goods_inv = "UPDATE `kkikda`.`js_auction_inventory` SET `amount`=0, reg_date=NOW() WHERE `goods_idx`='{$data->idx}';";
            $tradeapi->query_one($update_sql_auction_goods_inv);

            //history insert
            $search_item = "SELECT stock_number AS stock_num, price AS price FROM js_auction_goods WHERE idx='{$data->idx}';";
            $search_array = $tradeapi->query_list_object($search_item);

            $update_sql_history = "INSERT INTO `kkikda`.`js_auction_goods_history` (`idx`, `active`, `stock_number`, `pack_info`, `seller_userno`,`owner_userno`, `reg_date`, `nft_link`, `exchange_info`, price) 
            VALUES ('{$data->idx}','N', '{$search_array->stock_num}', '{$symbol}', '{$userno}', '1005', NOW(), '', '2', '{$search_array->price}');";
            $tradeapi->query_one($update_sql_history);
        }



        
    }

    $now = date('Y-m-d');

    $sql = array();

    // 판매 종목 정보 조회
    $items = $tradeapi->query_list_object("SELECT symbol, exchange FROM js_trade_currency WHERE active='Y' AND symbol<>'{$tradeapi->escape($tradeapi->default_exchange)}'");
    $cnt = count($items);
    for ($i = 0; $i < $cnt; $i++) {

        $item = $items[$i];
        $symbol = $item->symbol;
        $exchange = $item->exchange;
        $table_chart = 'js_trade_' . strtolower($symbol) . strtolower($exchange) . '_chart';

        if ($symbol && $exchange && $tradeapi->check_table_exists($table_chart)) {

            // 등급별로 쿼리를 만듧니다.
            $grades = array('S', 'A', 'B');
            foreach ($grades as $g) {

                // SELECT symbol, `date`, CLOSE, goods_grade, IFNULL(cnt*`close`, 0) amount FROM (
                //     SELECT 'GF0AP66RBP' symbol, tc.date, tc.close, tc.goods_grade, (SELECT SUM(confirmed) FROM js_exchange_wallet ew WHERE ew.symbol='GF0AP66RBP' AND ew.`goods_grade`=tc.`goods_grade` AND ew.userno>0 ) cnt
                //     FROM js_trade_gf0ap66rbpkrw_chart tc
                //     WHERE tc.term='1d' AND tc.date <= '2022-12-26 23:59:59' AND tc.goods_grade='S' 
                //     ORDER BY tc.date DESC LIMIT 1
                // ) t 
                $sql[] = "SELECT symbol, `date`, goods_grade, `close`, cnt, IFNULL(cnt*`close`, 0) amount FROM ( SELECT '{$symbol}' symbol, tc.date, tc.close, tc.goods_grade, (SELECT sum(confirmed) FROM js_exchange_wallet ew WHERE ew.symbol='{$symbol}' AND ew.`goods_grade`=tc.`goods_grade` AND ew.userno>0 ) cnt FROM {$table_chart} tc  WHERE tc.term='1d' AND tc.date <= '{$now} 23:59:59' AND tc.goods_grade='{$g}' ORDER BY tc.date DESC LIMIT 1 ) t ";

            }

        }
    }

    if($sql) {
        // 전체거래종목 평가금액
        $eval_amount = $tradeapi->query_one("select SUM(amount) from ( ".implode(' UNION ALL ', $sql)." ) t ");
        $text = ("INSERT INTO js_trade_index set `date`='{$now}', code='eval_amount', `value`='{$eval_amount}' ON DUPLICATE KEY UPDATE `value`='{$eval_amount}' ");
        
        // KKIKDA 지수
        $KKIDA = real_number($eval_amount/30000000, 2, 'round');
        $text = $text."////".("INSERT INTO js_trade_index set `date`='{$now}', code='kkikda', `value`='{$KKIDA}' ON DUPLICATE KEY UPDATE `value`='{$KKIDA}' ");

        
        //$tradeapi->query($test);
    }
        
    $r['msg'] = 'check : '.$text;
} else {
    $tradeapi->error('000', __('데이터형 오류'));
}

$tradeapi->success($r);
