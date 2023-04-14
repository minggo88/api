<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// validate parameters
$code = checkSymbol(strtoupper(checkEmpty($_REQUEST['code'], 'Index Code'))); // 인덱스 코드
$return_type = checkRetrunType(strtolower(setDefault($_REQUEST['return_type'], 'JSON'))); // 구매 화폐
$cnt = checkNumber(setDefault($_REQUEST['cnt'], '100')); // row수.
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


$tradeapi->set_cache_dir(__SRF_DIR__.'/cache/getTradeIndex/');
$cache_id = 'getTradeIndex-'.$code.'/'.$cnt;
$c = $tradeapi->get_cache($cache_id);
if($c=='') {
    $c = $tradeapi->set_cache($cache_id, $tradeapi->query_list_tsv("SELECT * FROM js_trade_index WHERE code='{$tradeapi->escape($code)}' ORDER BY `date` ASC"), $sec);
}
$tradeapi->clear_old_file($sec);

$tradeapi->success($c, $return_type);
