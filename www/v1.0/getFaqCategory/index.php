<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
// $tradeapi->checkLogin();
// $userno = $tradeapi->get_login_userno();

// validate parameters
// --------------------------------------------------------------------------- //

// 슬레이브 디비 사용하도록 설정.
$tradeapi->set_db_link('slave');

$lang = $tradeapi->get_i18n_lang();
$lang_c = preg_replace('/[^a-zA-Z]/','', $lang);
if($lang_c=='ko') $lang_c='kr';
if($lang_c=='zh') $lang_c='cn';

$query = "SELECT faqcode, IF(title_{$lang_c}='', `title_kr`, title_{$lang_c}) `title` FROM js_faq_info ORDER BY ranking ";
$c = $tradeapi->query_list_object($query);

// response
$tradeapi->success($c);
