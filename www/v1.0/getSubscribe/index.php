<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

$tradeapi->set_logging(true);
$tradeapi->set_log_dir(__dir__.'/../../log/'.basename(__dir__).'/');
$tradeapi->set_log_name(basename(__file__));
$tradeapi->write_log("REQUEST: " . json_encode($_REQUEST));

/**
 * 경매 생성/수정
 */
// 로그인 세션 확인.
$tradeapi->checkLogin();

$userid = $tradeapi->get_login_userid();
$userno = $tradeapi->get_login_userno();

$target_type    = checkEmpty($_REQUEST['target_type'], 'target_type');                 // 구독 대상 종류.  trade: 거래소(종목), auction: 경매(상품), shop: 쇼핑몰(상품), member: 회원, blog: 블로그, ...
$subscribe_type    = checkEmpty($_REQUEST['subscribe_type'], 'subscribe_type');     // 구독종류. like: "좋아요" 처리. subscribe:"구독"처리, notification:"알람"처리
if($subscribe_type!='like' && $subscribe_type!='subscribe' && $subscribe_type!='notification') {
    $tradeapi->error('102', __('구독종류를 올바르게 입력해주세요.'));
}
$target_idx    = setDefault($_REQUEST['target_idx'], '');                 // 구독 대상 회원번호

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('slave');

switch($subscribe_type) {
    case 'like': 
        $field_add .= ", s.like_date ";
        $where_add .= " AND s.like='Y' ";
        break;
    case 'subscribe': 
        $field_add .= ", s.subscribe_date ";
        $where_add .= " AND s.subscribe='Y' ";
        break;
    case 'notification': 
        $field_add .= ", s.notification_date ";
        $where_add .= " AND s.notification='Y' ";
        break;
}

// DB 쿼리
$sql = "SELECT s.subscriber_userno, s.target_type, s.target_idx {$field_add}";
switch($target_type) {
    case 'trade':
        $sql.= ", tc.name title, tc.icon_url image_url FROM js_subscribe s LEFT JOIN js_trade_currency tc ON s.target_idx=tc.symbol ";
        break;
    case 'auction':
        $sql.= ", ag.title title, ag.main_pic image_url FROM js_subscribe s LEFT JOIN js_auction_goods ag ON s.target_idx=ag.idx ";
        break;
    case 'shop':
        $sql.= ", sg.goods_name title, sg.img_goods_a image_url FROM js_subscribe s LEFT JOIN js_shop_goods sg ON s.target_idx=sg.idx ";
        break;
    case 'member':
        $sql.= ", m.name title, m.image_profile_url image_url FROM js_subscribe s LEFT JOIN js_member m ON s.target_idx=m.userno ";
        break;
}

$where = "WHERE s.subscriber_userno='{$tradeapi->escape($userno)}'";
if($target_idx) { $where.= " AND s.target_idx='{$tradeapi->escape($target_idx)}' ";}
if($target_type) { $where.= " AND s.target_type='{$tradeapi->escape($target_type)}' ";}


$sql.= "{$where} ORDER BY reg_date DESC";
$payload = $tradeapi->query_fetch_object($sql);

// response
$tradeapi->success($payload??array());
