<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
// $tradeapi->checkLogin();

$userid = $tradeapi->get_login_userid();
$userno = $tradeapi->get_login_userno();

// validate parameters
$goods_idx  = checkEmpty($_REQUEST['goods_idx'], 'goods_idx');          // goods_idx

// 슬레이브 디비 사용하도록 설정.
$tradeapi->set_db_link('slave');

//회원별 인벤토리
// $query ="SELECT g.idx, g.title, g.content, g.main_pic, g.sub1_pic, g.sub2_pic, g.sub3_pic, g.sub4_pic, g.goods_type, g.reg_date, g.mod_date, i.userid,
//     IF((SELECT auction_price FROM js_auction_apply WHERE goods_idx=i.goods_idx) <>'',
//     (SELECT auction_price FROM js_auction_apply WHERE goods_idx=i.goods_idx), i.buy_price) AS price
//     FROM js_auction_inventory AS i INNER JOIN js_auction_goods AS g ON i.goods_idx=g.idx
//     where g.idx='{$tradeapi->escape($goods_idx)}'";
$query ="SELECT g.*, 
    i.userid,
    IF((SELECT auction_price FROM js_auction_apply WHERE goods_idx=i.goods_idx) <>'',
    (SELECT auction_price FROM js_auction_apply WHERE goods_idx=i.goods_idx), i.buy_price) AS price
    FROM js_auction_goods AS g LEFT JOIN js_auction_inventory AS i ON i.goods_idx=g.idx
    where g.idx='{$tradeapi->escape($goods_idx)}'";

$goods_info = (array) $tradeapi->query_fetch_object($query);

// 메타데이터 정보 추가
$meta_data = $tradeapi->db_get_list('js_auction_goods_meta', array('goods_idx'=>$goods_info['idx']));
foreach($meta_data as $row) {
    $goods_info[$row->meta_key] = $row->meta_val;
}
// 인증마크 정보 추가
if($goods_info['meta_certification_mark']) {
    $_cm_info = $tradeapi->db_get_row('js_auction_certification_marks', array('idx'=>$goods_info['meta_certification_mark']));
    $goods_info['meta_certification_mark_name'] = $_cm_info->title ?? '';
    $goods_info['meta_certification_mark_image'] = $_cm_info->image_url ?? '';
}


// response
$tradeapi->success($goods_info);