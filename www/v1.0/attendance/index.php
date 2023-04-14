<?php
include dirname(__file__) . "/../../lib/ExchangeApi.php";

/**
 * 인증코드 확인
 */

// 로그인 세션 확인.
$exchangeapi->checkLogin();
$userno = $exchangeapi->get_login_userno();

// validate parameters

// --------------------------------------------------------------------------- //

// 마스터 디비 사용하도록 설정.
$exchangeapi->set_db_link('master');


// 출석채크 환경값.
$sql = "select * from js_attendance_setup limit 1";
$cache_id = md5($sql);
$cachetime = 60;
$row = $exchangeapi->get_cache($cache_id);
if($row=='') {
    $row = $exchangeapi->set_cache($cache_id, $exchangeapi->query_fetch_array($sql), $cachetime);
}
$exchangeapi->clear_old_file($cachetime);
// 지급 화폐
$symbol = $row['symbol'];
// 출석시작 시간  ex: 00:00:00  시:분:초
$att_start_time = $row['att_start_time'];
// 출석종료 시간  ex: 23:59:59 시:분:초
$att_end_time = $row['att_end_time'];
// 일일 출석 포인트
$att_day_point = $row['att_day_point'];
// 7일 개근 포인트
$att_week_point = $row['att_week_point'];
// 30일 개근 포인트
$att_month_point = $row['att_month_point'];
// 365일 개근 포인트
$att_year_point = $row['att_year_point'];
// 1등 포인트
$att_first_point = $row['att_first_point'];
// 2등 포인트
$att_second_point = $row['att_second_point'];
// 3등 포인트
$att_third_point = $row['att_third_point'];
// 출석부 조회 일 :  몇일까지 출석부 조회 가능한가?
$att_check_day = $row['att_check_day'];

// 출석 날짜 / 시간
$today_date = date("Y-m-d");
$today_time = date("H:i:s");

// 출석 시간 체크
if ($today_time < $att_start_time || $today_time > $att_end_time) {
    $exchangeapi->error('000',__('Not attendance time.')); //출석 시간이 아닙니다.
}

// 오늘 출석했나?
$sql = " select * from js_attendance where userno = '{$exchangeapi->escape($userno)}' and reg_date = '".$today_date."' ";
$check = $exchangeapi->query_fetch_array($sql);

// 출석했다면.
if ($check['userno']) {
    $exchangeapi->error('000', __('You are already attendance.'));//이미 출석 하였습니다.
}


// 받는 사람 지갑
$receiver_wallet = $exchangeapi->get_wallet($userno, $symbol);
// 보내는 사람 지갑은 없음.


// 총출석일수
$sql = " SELECT MAX(sumday) as sumday FROM js_attendance WHERE userno = '{$exchangeapi->escape($userno)}' ";
$row = $exchangeapi->query_fetch_array($sql);
// 총출석일
$sumday = $row['sumday'] + 1;


// 1일 뺀다.
$day = date("Y-m-d", time() - (1 * 86400));

// 어제 출석했나?
$sql = " select * from js_attendance where userno = '{$exchangeapi->escape($userno)}' and reg_date = '{$exchangeapi->escape($day)}' ";
$row = $exchangeapi->query_fetch_array($sql);

// 1일 포인트
$sql_point = $att_day_point;
$sql_bonus = 'N';

// 어제 출석했다면
if ($row['userno']) {
    // 전체 개근에 오늘 합산
    $sql_day = $row['day'] + 1;

    // 지난 개근체크에 오늘 합산
    $sql_reset = $row['reset'] + 1;
    $sql_reset2 = $row['reset2'] + 1;
    $sql_reset3 = $row['reset3'] + 1;

    // if ($sql_reset == 7) { // 7일 개근
    //     $sql_reset  = "0";
    //     $sql_point  += $att_week_point;
    // }
	// if ($sql_reset2 == 30) { // 30일 개근
    //     $sql_reset2 = "0";
    //     $sql_point  += $att_month_point;
    // }
	// if ($sql_reset3 == 365) {  // 365일 개근
    //     $sql_reset3 = "0";
    //     $sql_point  += $att_year_point;
    // }

    // 1주일 개근

    if (date('d')%7 == 0) { // 오늘이 매 7일이면
        if ($sql_reset == 7) { // 오늘까지 7번 채웠으면 보너스 지급.
            $sql_point  += $att_week_point;
            $sql_bonus = 'Y';
        }
        $sql_reset  = "0";
    }
	// 1달 개근
    if (date('d') == date('t')) { // 오늘이 이번달 마지막 날짜면
        if ($sql_reset2 == date('t')) { // 오늘까지 이번달의 날짜만큼 채웠으면 보너스 지급.
            $sql_point  += $att_month_point;
            $sql_bonus = 'Y';
        }
        $sql_reset2 = "0";
    }
    // 1년 개근
    $current_year = date('Y');
    $diff = date_diff( date_create($current_year.'-01-01'), date_create(($current_year + 1).'-01-01'));
    if ($today_date == date('Y-m-t', strtotime($current_year.'-12-01'))) { // 오늘이 올해의 마지막 날짜면
        if ($sql_reset3 == $diff->days) { // 오늘까지 이번달의 날짜만큼 채웠으면 보너스 지급.
            $sql_point  += $att_year_point;
        }
        $sql_reset3 = "0";
    }
} else { // 출석하지 않았다면
    // 전체 개근 설정
    $sql_day = "1";
    // 리셋
    $sql_reset  = "1";
    $sql_reset2 = "1";
    $sql_reset3 = "1";
}

// 첫출근
$sql = " SELECT COUNT(*) AS cnt, IFNULL(MAX(`rank`),0) AS `rank` FROM js_attendance WHERE reg_date = '".$today_date."' ";
$first = $exchangeapi->query_fetch_array($sql);

$rank = ""; // 1,2,3등 빼고 나머지는 등수 없음.
if (!$first['cnt'] && $first['rank'] < 1) { // 1등 포인트
    if($att_first_point>0) {
        $sql_point += $att_first_point;
        $sql_bonus = 'Y';
    }
	$rank = 1;
} elseif ($first['cnt'] == 1 && $first['rank'] < 2) { // 2등 포인트
    if($att_second_point>0) {
        $sql_point += $att_second_point;
        $sql_bonus = 'Y';
    }
	$rank = 2;
} elseif ($first['cnt'] == 2 && $first['rank'] < 3) { // 3등 포인트
    if($att_third_point>0) {
        $sql_point += $att_third_point;
        $sql_bonus = 'Y';
    }
	$rank = 3;
}


// 기록
$sql = " insert into js_attendance set userno = '{$exchangeapi->escape($userno)}', day = '{$exchangeapi->escape($sql_day)}',sumday = '{$exchangeapi->escape($sumday)}', reset = '{$exchangeapi->escape($sql_reset)}', reset2 = '{$exchangeapi->escape($sql_reset2)}', reset3 = '{$exchangeapi->escape($sql_reset3)}', point = '{$exchangeapi->escape($sql_point)}', bonus = '{$exchangeapi->escape($sql_bonus)}', `rank` = '{$exchangeapi->escape($rank)}', reg_date = '{$exchangeapi->escape($today_date)}', reg_time = '{$exchangeapi->escape($today_time)}' ";
// exit($sql); // insert into js_attendance set userno = '1322', day = '1',sumday = '1', reset = '1', reset2 = '1', reset3 = '1', point ='5', bonus = 'N', rank = '1', reg_date = '2019-08-13', reg_time = '13:26:21'
$r = $exchangeapi->query($sql);
if(!$r) {
    $exchangeapi->error('005',__('A system error has occurred.'));
}

// 출석 포인트 지급
// insert_point($userno, (int)($sql_point * 1), "출석 포인트", "@attendance", $member['mb_id'], $today_date);
$r = $exchangeapi->add_wallet_txn ($userno, $receiver_wallet->address, $symbol, '', 'A', 'I', $sql_point, 0, 0, 'D', $attendanceid, date('Y-m-d H:i:s'));// DB 처리라 상태는 완료로 처리함. ,
if(!$r) {
    $exchangeapi->query("DELETE FROM js_attendance WHERE userno='{$exchangeapi->escape($userno)}' AND reg_date='{$exchangeapi->escape($today_date)}'   ");
    $exchangeapi->error('005',__('A system error has occurred.'));
}
$txnid = $exchangeapi->_recently_query['last_insert_id'];
$r = $exchangeapi->add_wallet($userno, $symbol, $sql_point); // 받는 사람의 지갑번호가 있는경우만 받는사람 지갑에 잔액 추가. 없는 경우 외부 지갑이라 패스.
if(!$r) {
    $exchangeapi->query("DELETE FROM js_attendance WHERE userno='{$exchangeapi->escape($userno)}' AND reg_date='{$exchangeapi->escape($today_date)}'   ");
    $exchangeapi->query("DELETE FROM js_exchange_wallet_txn WHERE id='{$exchangeapi->escape($txnid)}");
    $exchangeapi->error('005',__('A system error has occurred.'));
}

// response
$exchangeapi->success(true);
