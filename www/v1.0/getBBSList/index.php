<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
// $tradeapi->checkLogin();
// $userno = $tradeapi->get_login_userno();

// validate parameters
$bbscode = checkEmpty($_REQUEST['bbscode'], 'bbscode');
$last_idx = checkNumber(setDefault($_REQUEST['last_idx'], 0));
$page = checkNumber(setDefault($_REQUEST['page'], '1'));
$rows = checkNumber(setDefault($_REQUEST['rows'], '10'));
// $limit = checkNumber(setDefault($_REQUEST['limit'], 20));
$start = ($page-1) * $rows;
if($last_idx) {$start = 0;}
// --------------------------------------------------------------------------- //

// 슬레이브 디비 사용하도록 설정.
$tradeapi->set_db_link('slave');

$query = 'SELECT count(idx) FROM js_bbs_main where bbscode=\''.$bbscode.'\'';
$total = $tradeapi->query_one($query);
$lang = $tradeapi->get_i18n_lang();
$lang_c = preg_replace('/[^a-zA-Z]/','', $lang);
if($lang_c=='ko') $lang_c='kr';
if($lang_c=='zh') $lang_c='cn';


if ($bbscode=="NOTICE") {
    $query = "SELECT idx, bbscode, userid, author, IF(subject_{$lang_c}='', subject_kr, subject_{$lang_c}) `subject`, IF(contents_{$lang_c}='', contents_kr, contents_{$lang_c}) `contents`, `website`, `file`, `file_src`, `hit`, regdate regtime, FROM_UNIXTIME(regdate) `regdate`, division FROM js_bbs_main WHERE bbscode='{$tradeapi->escape($bbscode)}' ";
    $query.= " and division = 'a' ";
    $query.= " ORDER BY idx DESC LIMIT {$tradeapi->escape($start)}, {$tradeapi->escape($rows)}";
    $notice_arr = $tradeapi->query_list_object($query);
}

$query = "SELECT idx, bbscode, userid, author, IF(subject_{$lang_c}='', subject_kr, subject_{$lang_c}) `subject`, IF(contents_{$lang_c}='', contents_kr, contents_{$lang_c}) `contents`, `website`, `file`, `file_src`, `hit`, regdate regtime, FROM_UNIXTIME(regdate) `regdate`, division FROM js_bbs_main WHERE bbscode='{$tradeapi->escape($bbscode)}' ";
if($last_idx) { $query.= " and idx<'{$tradeapi->escape($last_idx)}' "; }
if ($bbscode=="NOTICE") {
    $query.= " and division = 'b' ";
}
$query.= " ORDER BY idx DESC LIMIT {$tradeapi->escape($start)}, {$tradeapi->escape($rows)}";

$c = $tradeapi->query_list_object($query);

if ($bbscode=="NOTICE") {
    $c = array_merge($notice_arr, $c);
}

// response
$tradeapi->success(array('data'=>$c, 'total'=>$total));
