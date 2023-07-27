<?php

/**
 * 거래소 지수 데이터 생성.
 * 
 * 실행 명령어: 
 * $ php genIndex.php start
 * 종료 명령어:
 * $ php genChart.php stop
 */
include(dirname(__file__) . '/../lib/TradeApi.php');

ignore_user_abort(1);
set_time_limit(0);

$tradeapi->logging = false;
$tradeapi->set_log_dir(dirname(__file__) . '/../log/' . basename(__file__, '.php') . '/');
$tradeapi->set_log_name('');
$tradeapi->write_log('genIndex.php start.');

$filename = __file__;

// 프로세스 작동중인지 확인. 작동중이면 종료.
@exec("ps  -ef| grep -i '{$filename}' | grep -v grep", $output);
if (count($output) > 1) {
    $tradeapi->write_log('프로세스 중복으로 종료.');
    exit();
}

$tradeapi->set_db_link('master');

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
    $tradeapi->query("INSERT INTO js_trade_index set `date`='{$now}', code='eval_amount', `value`='{$eval_amount}' ON DUPLICATE KEY UPDATE `value`='{$eval_amount}' ");
    
    // KKIKDA 지수
    $KKIDA = real_number($eval_amount/30000000, 2, 'round');
    $tradeapi->query("INSERT INTO js_trade_index set `date`='{$now}', code='kkikda', `value`='{$KKIDA}' ON DUPLICATE KEY UPDATE `value`='{$KKIDA}' ");

    $nowTime = time();
    $ctime = date('i', $nowTime);
    
    if ($ctime % 10 === 0) {
        $test = "INSERT INTO js_test set `text1` = '{$nowTime}', `tvalue` = '{$ctime}' ";
        $tradeapi->query($test);
    }
}

$tradeapi->write_log('genIndex.php end.');