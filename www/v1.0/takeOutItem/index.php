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
            $update_sql_exchage_wallet = "UPDATE `kkikda`.`js_exchange_wallet` SET `confirmed`= `confirmed`-1, moddate=NOW() WHERE  `userno`='{$userno}' AND `symbol`='{$symbol}';";
            $tradeapi->query_one($update_sql_exchage_wallet);

            $update_sql_exchange_wallet_nft = "UPDATE `kkikda`.`js_exchange_wallet_nft` SET `amount`='0' WHERE  `symbol`='{$symbol}' AND `tokenid`='{$data->idx}';";
            $tradeapi->query_one($update_sql_exchange_wallet_nft);
            
            //auction_goods
            $update_sql_auction_goods = "UPDATE `kkikda`.`js_auction_goods` SET `active`='N', owner_userno = '1005', mode_date=NOW() WHERE  `idx`='{$data->idx}' AND `owner_userno` = '{$userno}';";
            $tradeapi->query_one($update_sql_auction_goods);

            $update_sql_auction_goods_inv = "UPDATE `kkikda`.`js_auction_inventory` SET `amount`=0, reg_date=NOW() WHERE `goods_idx`='{$data->idx}';";
            $tradeapi->query_one($update_sql_auction_goods_inv);

            //history insert
            $search_item = "SELECT stock_number FROM js_auction_goods WHERE idx='{$data->idx}';";
            $search_array = $tradeapi->query_list_object($search_item);

            $search_item = "SELECT stock_number FROM js_auction_goods WHERE idx='{$data->idx}';";
            $stock_number = $tradeapi->query_one($search_item);

            $insert_sql_history = "INSERT INTO `kkikda`.`js_auction_goods_history` (`idx`, `active`, `stock_number`, `pack_info`, `seller_userno`,`owner_userno`, `reg_date`, `nft_link`, `exchange_info`) 
            VALUES ('{$data->idx}','N', '{$stock_number}', '{$symbol}', '{$userno}', '1005', NOW(), '', '2');";
            $tradeapi->query_one($insert_sql_history);
        }
    }
        
    $r['msg'] = 'check : '.$text;
} else {
    $tradeapi->error('000', __('데이터형 오류'));
}

$tradeapi->success($r);
