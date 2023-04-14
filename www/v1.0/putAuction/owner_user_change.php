<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

$tradeapi->set_logging(true);
$tradeapi->set_log_dir(__dir__.'/../../log/'.basename(__dir__).'/');
$tradeapi->set_log_name(basename(__file__));
$tradeapi->write_log("REQUEST: " . json_encode($_REQUEST));

// 로그인 세션 확인.
//$tradeapi->checkLogin();

$goods_idx      = checkEmpty($_REQUEST['goods_idx'], 'goods_idx');
$owner_no       = $_REQUEST['owner_no'];
$owner_name     = $_REQUEST['owner_name'];
$type           = checkEmpty($_REQUEST['type'], 'type');  // type : memberList -> 회원 정보 리스트, ownerChange
$search_name    = setDefault($_REQUEST['search_name'], '');


if ($type == "ownerChange") {
    $goods_info = $tradeapi->query_fetch_object("SELECT * FROM js_auction_goods WHERE idx='{$tradeapi->escape($goods_idx)}' limit 1");

    if(!$goods_info || !$goods_info->idx){
        $tradeapi->error('500', __('상품 정보를 찾지 못했습니다.'));
    }

    $member_info = $tradeapi->query_fetch_object("select * from js_member where userno='{$tradeapi->escape($owner_no)}' limit 1");
    if(!$member_info || !$member_info->userno){
        $tradeapi->error('501', __('사용자 정보를 찾지 못했습니다.'));
    }

    $owner_userno = $member_info->userno;
    $owner_userid = $member_info->userid;
    $owner_name = $member_info->name;

    $tradeapi->query("UPDATE js_auction_goods SET owner_userno = '{$owner_userno}' WHERE idx='{$goods_info->idx}' ");

    $goods_inventory_info = $tradeapi->query_fetch_object("SELECT * FROM js_auction_inventory WHERE goods_idx='{$tradeapi->escape($goods_idx)}' limit 1");
    if ($goods_inventory_info || $goods_inventory_info->idx) {
        $tradeapi->query("UPDATE js_auction_inventory SET userno='{$owner_userno}', userid='{$owner_userid}' WHERE goods_idx='{$tradeapi->escape($goods_idx)}' ");
    }

    $tradeapi->success(array('goods_idx'=> $goods_idx));
} else if($type == "memberList") {
    $sql = "SELECT * FROM js_member where `name` like '%{$tradeapi->escape($search_name)}%' ";
    $payload = $tradeapi->query_list_object($sql);

    // response
    $tradeapi->success($payload);
}




