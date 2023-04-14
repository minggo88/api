<?php
include dirname(__file__) . "/../../lib/ExchangeApi.php";

// validate parameters
$code = setDefault($_REQUEST['code'], '');

// --------------------------------------------------------------------------- //

// 슬레이브 디비 사용하도록 설정.
$exchangeapi->set_db_link('slave');

$r = array(
    array('code'=>'en', 'name'=>'english')
    ,array('code'=>'ko', 'name'=>'한국어')
    ,array('code'=>'zh', 'name'=>'中文')
);

$exchangeapi->success($r);
