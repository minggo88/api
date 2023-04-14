<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
// $tradeapi->checkLogin();
// $userno = $tradeapi->get_login_userno();

// validate parameters
$faqcode = setDefault($_REQUEST['faqcode'], ''); // 카테고리 코드
$last_idx = checkNumber(setDefault($_REQUEST['last_idx'], 0));
$page = checkNumber(setDefault($_REQUEST['page'], '1'));
$rows = checkNumber(setDefault($_REQUEST['rows'], '10'));
$start = ($page-1) * $rows;
if($last_idx) {$start = 0;}
// --------------------------------------------------------------------------- //

// 슬레이브 디비 사용하도록 설정.
$tradeapi->set_db_link('slave');

$query = 'SELECT count(idx) FROM js_faq where 1 ';
if($faqcode) { $query.= " AND faqcode='{$tradeapi->escape($faqcode)}' "; }
$total = $tradeapi->query_one($query);
$lang = $tradeapi->get_i18n_lang();
$lang_c = preg_replace('/[^a-zA-Z]/','', $lang);
if($lang_c=='ko') $lang_c='kr';
if($lang_c=='zh') $lang_c='cn';

$query = "SELECT idx, faqcode, IF(subject_{$lang_c}='', `subject_kr`, subject_{$lang_c}) `subject`, IF(contents_{$lang_c}='', contents_kr, contents_{$lang_c}) `contents` FROM js_faq WHERE 1 ";
if($faqcode) { $query.= " AND faqcode='{$tradeapi->escape($faqcode)}' "; }
if($last_idx) { $query.= " AND idx<'{$tradeapi->escape($last_idx)}' "; }
$query.= " ORDER BY idx DESC LIMIT {$tradeapi->escape($start)}, {$tradeapi->escape($rows)}";
$c = $tradeapi->query_list_object($query);

// response
$tradeapi->success(array('data'=>$c, 'total'=>$total));
