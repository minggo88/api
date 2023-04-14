<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
// $tradeapi->checkLogin();
// $userno = $tradeapi->get_login_userno();

// validate parameters
$idx = addHit(loadParam('idx'));

// --------------------------------------------------------------------------- //

// 슬레이브 디비 사용하도록 설정.
$tradeapi->set_db_link('slave');

$lang = $tradeapi->get_i18n_lang();
$lang_c = preg_replace('/[^a-zA-Z]/','', $lang);
if($lang_c=='ko') $lang_c='kr';
if($lang_c=='zh') $lang_c='cn';

$r = $tradeapi->query_fetch_object("SELECT idx, bbscode, userid, author, IF(subject_{$lang_c}='', subject_kr, subject_{$lang_c}) `subject`, IF(contents_{$lang_c}='', contents_kr, contents_{$lang_c}) `contents`, website, file, hit, regdate regtime, FROM_UNIXTIME(regdate) regdate FROM js_bbs_main where idx='{$tradeapi->escape($idx)}'");

// response
$tradeapi->success($r);
