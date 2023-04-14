<?php
/**
 * 전체 거래량 차트 데이터를 생성합니다.
 * 날짜(date), 총거래량(volume) 만 리턴합니다.
 */
include dirname(__file__) . "/../../lib/TradeApi.php";

// validate parameters
$return_type = checkRetrunType(strtolower(setDefault($_REQUEST['return_type'], 'JSON'))); // 구매 화폐
$period = setDefault($_REQUEST['period'], '1d'); // 봉차트 기간. 1m, 3m, 5m, 10m, 15m, 30m, 1h, 12h, 1d, 1w, 1M
$cnt = checkNumber(setDefault($_REQUEST['cnt'], '1000')); // row수.

// --------------------------------------------------------------------------- //

// 슬레이브 디비 사용하도록 설정.
$tradeapi->set_db_link('slave');

$sec = 60*60; // 최대 1시간.
switch($period) {
    case '1m': $sec = 10; break;
    case '3m': $sec = 60; break;
    case '5m': $sec = 60; break;
    case '10m': $sec = 120; break;
    case '15m': $sec = 120; break;
    case '30m': $sec = 120; break;
    case '1h': $sec = 120; break;
    case '1d': $sec = 1800; break;
}
$sec = 2; // 그냥 2초로 수정. 너무 안변함.

$tradeapi->set_cache_dir(__SRF_DIR__.'/cache/getChartDataTotalVolume/');
$cache_id = 'getChartDataTotalVolume-/'.$period.'/'.$cnt;
$c = $tradeapi->get_cache($cache_id);
if($c=='') {

    $currencies = $tradeapi->query_list_object('select symbol, exchange from js_trade_currency');
    $t = array();
    foreach($currencies as $c) {
        $table = 'js_trade_'.strtolower($c->symbol).strtolower($c->exchange).'_chart';
        $r = $tradeapi->check_table_exists($table);
        if($r) {
            $t[] = $table;
        }
    }

    $sql = "
    SELECT `date`, SUM(volume) volume
    FROM (";
    for($i=0; $i<count($t); $i++) {
        if($i>0) $sql.=" UNION ALL ";
        $sql.="SELECT `date`, volume FROM {$t[$i]} FORCE INDEX(PRIMARY) WHERE term = '1d'";
    }
    $sql.= "
    ORDER BY `date` DESC
    )t
    GROUP BY t.date
    LIMIT {$cnt}
    ";
    $c = $tradeapi->query_list_tsv($sql, true);
    $c = $tradeapi->set_cache($cache_id, $c, $sec);
}
$tradeapi->clear_old_file($sec);

$tradeapi->success($c, $return_type);
