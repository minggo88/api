<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
// $tradeapi->checkLogin();
// $userno = $tradeapi->get_login_userno();

// validate parameters
$bbscode = checkEmpty($_REQUEST['bbscode'], 'bbscode');
$cnt = checkNumber(setDefault($_REQUEST['cnt'], '5'));
$by_category = resetYN($_REQUEST['by_category'], 'Y');
// --------------------------------------------------------------------------- //

// 슬레이브 디비 사용하도록 설정.
$tradeapi->set_db_link('slave');

$payload = array();

$lang_db = $tradeapi->get_i18n_lang_db();

$query = "SELECT idx, category, bbscode, userid, author, email, hit, cnt_comment, regdate regtime, FROM_UNIXTIME(regdate) regdate, file, file_src,
IF(subject_{$lang_db}='', subject_kr, subject_{$lang_db}) `subject`, 
IF(contents_{$lang_db}='', contents_kr, contents_{$lang_db}) `contents` 
FROM js_bbs_main 
WHERE bbscode='{$tradeapi->escape($bbscode)}' "; 

if($by_category=='Y') {

    $category = $tradeapi->query_one("SELECT bbs_category FROM js_bbs_info WHERE bbscode='{$tradeapi->escape($bbscode)}' ");// 카태고리 추출
    $category = $category ? explode(',', $category) : array();
    foreach($category as $c) {
        // 공지글 전부
        $r = $tradeapi->query_list_assoc($query." AND division='a' AND category='{$tradeapi->escape($c)}' ORDER BY idx DESC LIMIT $cnt ");// 카태고리별 공지글 5개 추출 //  a : 공지글 , b: 일반글
        // 공지글 없으면 최근 글 5개
        if(count($r) < $cnt) {
            $cnt = $cnt - count($r);
            $r2 = $tradeapi->query_list_assoc($query." AND category='{$tradeapi->escape($c)}'  ORDER BY idx DESC LIMIT $cnt "); // 카태고리별 공지글 
            $r2 ? $r = array_merge( $r, $r2)  : $r;
        }
        $payload[$c] = $r;
    }
    
} else {
    
    // 공지글 전부
    $r = $tradeapi->query_list_assoc($query." AND division='a' ORDER BY idx DESC LIMIT $cnt ");// 카태고리별 공지글 5개 추출 //  a : 공지글 , b: 일반글
    // 공지글 없으면 최근 글 5개
    if(count($r) < $cnt) {
        $cnt = $cnt - count($r);
        $r2 = $tradeapi->query_list_assoc($query." ORDER BY idx DESC LIMIT $cnt "); // 카태고리별 공지글 
        $r2 ? $r = array_merge( $r, $r2)  : $r;
    }
    $payload = $r;

}

// response
$tradeapi->success($payload);
