<?php
define('__API_RUNMODE__', 'dev');
include ('../lib/TradeApi.php');

$tables = array('_chart', '_order', '_ordertxn', '_txn', '_quote');
$currency = $tradeapi->query_list_object("SELECT symbol,exchange FROM js_trade_currency ");
foreach($currency as $c) {
    foreach($tables as $t) {
        $table_name = 'js_trade_'.strtolower($c->symbol).strtolower($c->exchange).$t;
        if($tradeapi->check_table_exists($table_name)) {
            
            // if($t=='_chart') {
            //     echo("ALTER TABLE `{$table_name}` CHANGE `goods_grade` `goods_grade` CHAR(1) CHARSET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL COMMENT '상품 등급 (S,A,B)' AFTER `date`, DROP PRIMARY KEY, ADD PRIMARY KEY (`term`, `date`, `goods_grade`);   \n"); //exit;
            // }
            // echo(" UPDATE `{$table_name}` SET goods_grade='A' WHERE goods_grade=''; \n"); //exit;
            // echo("ALTER TABLE `{$table_name}` CHANGE `cha` `goods_grade` CHAR(1) CHARSET utf8mb3 DEFAULT 'A' NOT NULL COMMENT '상품 등급 (S,A,B)';  \n"); //exit;
            // echo("ALTER TABLE {$table_name} ADD `goods_grade` CHAR(1) DEFAULT 'A' NOT NULL COMMENT '상품 등급 (S,A,B)'; \n"); //exit;
            // ALTER TABLE `kkikda`.`js_trade_gbulf3lzmukrw_chart_copy` ADD COLUMN `goods_grade` CHAR(1) DEFAULT 'A' NULL COMMENT '상품 등급 (S,A,B)' AFTER `volume`; 

            // $tradeapi->query("ALTER TABLE {$table_name} ADD `goods_grade` CHAR(1) DEFAULT 'A' NOT NULL COMMENT '상품 등급 (S,A,B)' ");
        }
    }
}
