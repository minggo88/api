<?php
include dirname(__file__) . "/../../lib/ExchangeApi.php";

/**
 * 인증코드 확인
 */

// 로그인 세션 확인.
$exchangeapi->checkLogin();
$userno = $exchangeapi->get_login_userno();

// validate parameters
$year = checkNumber(setDefault(loadParam('year'), date('Y'))); // 검색 년
$month = checkNumber(setDefault(loadParam('month'), date('m'))); // 검색 월

// var_dump($year, $month); exit;

// --------------------------------------------------------------------------- //

// 출석날짜
$sql = "SELECT CONCAT(reg_date, ' ', reg_time) AS regdate, point, bonus FROM js_attendance WHERE reg_date>='{$year}-{$month}-01' AND reg_date<='{$year}-{$month}-31' AND userno = '{$exchangeapi->escape($userno)}' ";
$r = $exchangeapi->query_list_object($sql);

// response
$exchangeapi->success($r);
