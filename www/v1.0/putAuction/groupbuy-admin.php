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
//$tradeapi->checkLogin();

$userid = $tradeapi->get_login_userid();
$userno = $tradeapi->get_member_info(2)->userno;


$auction_idx    = setDefault($_REQUEST['auction_idx'], '');                 //경매번호. 경매시작전 수정할때 사용
$goods_idx      = setDefault($_REQUEST['goods_idx'], '');          //경매상품번호
$start_price    = setDefault($_REQUEST['start_price'], 0);      //판매시작금액
$sell_price     = setDefault($_REQUEST['sell_price'], '0');                 //판매완료금액.미사용
$sell_volume     = setDefault($_REQUEST['sell_volume'], '1');                 //판매수량(미분할 상품은 항상1, 분할된 상품은 1이상)
$split_volume     = setDefault($_REQUEST['split_volume'], '');                 //분할수량(미분할 상품은 항상1, 분할된 상품은 1이상, 전체 보유자만 분할)
$wish_price     = setDefault($_REQUEST['wish_price'], '0');                 //희망금액
if($wish_price>0  && $start_price>$wish_price) {
    $tradeapi->error('520', __('Please enter a higher value than the starting price.'));
}
$unit_price     = setDefault($_REQUEST['unit_price'], '0');                 //호가 간격 가격
$start_date     = checkEmpty($_REQUEST['start_date'], 'start_date');        //시작일
$end_date       = checkEmpty($_REQUEST['end_date'], 'end_date');            //종료일
$auction_title  = checkEmpty($_REQUEST['auction_title'], 'auction_title');  //옥션 제목
$price_symbol   = setDefault($_REQUEST['price_symbol'], 'KRW');             //거래화폐(가격)심볼
$buy_now        = resetYN($_REQUEST['buy_now'], 'Y');                    //바로구매기능 사용여부

$start_date     = date("Y-m-d H:i:s", strtotime($start_date));
$end_date       = date("Y-m-d H:i:s", strtotime($end_date));

$sell_type       = setDefault($_REQUEST['sell_type'], 'G');                    //판매방식, F:고정가판매(최소가=바로구매가), E:영국식경매(최고가낙찰), D:네덜란드경매(역경매), G: 공동구매
$active              = setDefault($_REQUEST['active'], 'N');                       //'사용여부. Y:사용, N:미사용'
$content             = setDefault($_REQUEST['content'], '');                       //내용
$detail_contents     = setDefault($_REQUEST['detail_contents'], '');               //상세내용
$main_pic     = setDefault($_REQUEST['main_pic'], '');               //상세내용
$sub1_pic     = setDefault($_REQUEST['sub1_pic'], '');               //상세내용
$sub2_pic     = setDefault($_REQUEST['sub2_pic'], '');               //상세내용
$sub3_pic     = setDefault($_REQUEST['sub3_pic'], '');               //상세내용
$sub4_pic     = setDefault($_REQUEST['sub4_pic'], '');               //상세내용

$title  = setDefault($_REQUEST['title'], '');                                       //제품제목
$sub_title  = setDefault($_REQUEST['sub_title'], '');                               //서브제목

// 이미지 s3 정식폴더로 이동
$s3_check_param = array('main_pic', 'sub1_pic', 'sub2_pic', 'sub3_pic', 'sub4_pic');
foreach($s3_check_param as $param) {
    $file = $$param;
    if($file && strpos($file, '.s3.')!==false && strpos($file, '/tmp/')!==false) {
        $$param = $tradeapi->move_tmpfile_to_s3($file);
    }
}

// 가격 소숫점자릿수 수정(버림)
$price_currency_info = $tradeapi->query_fetch_object("SELECT base_coin, auction_manager_userno, display_decimals FROM js_exchange_currency ec LEFT JOIN js_auction_currency ac ON ec.symbol=ac.symbol WHERE ec.symbol='{$tradeapi->escape($price_symbol)}' ");
$start_price    = $start_price>0 ? real_number($start_price, $price_currency_info->display_decimals, 'floor') : $start_price;
$wish_price    = $wish_price>0 ? real_number($wish_price, $price_currency_info->display_decimals, 'floor') : $wish_price;

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('master');

//제품 있나? 경매정보 등록/수정이지만 상품이 있냐 없냐가 중요해서 상품정보를 기준으로 Left Join합니다.
$goods_info = $tradeapi->query_fetch_object("SELECT g.nft_symbol, g.nft_id, g.idx goods_idx, g.goods_type, g.nft_max_supply, g.pack_info, i.userno, l.auction_idx,l.auction_title,l.start_date,l.end_date,l.start_price,l.sell_price,l.wish_price,l.creator_userno,l.sell_volume, l.finish finish,IF(l.start_date<=NOW(), 'Y', 'N') AS started  FROM js_auction_goods as g LEFT JOIN js_auction_inventory as i on g.idx=i.goods_idx LEFT JOIN js_auction_list as l on g.idx=l.goods_idx AND l.finish<>'Y' WHERE g.idx='{$tradeapi->escape($goods_idx)}'"); // AND l.finish='N' AND NOW()<l.end_date 
// ,IF(l.end_date<NOW() OR l.finish='Y', 'Y', 'N') AS finish  종료날짜 입력 오류가 있을 수 있어 finish 값만 가지고 확인하도록 수정함.
if($goods_idx && (!$goods_info || !$goods_info->goods_idx)){
    $tradeapi->error('500', __('상품 정보를 찾지 못했습니다.'));
}
// var_dump($goods_info); exit;

// $total_goods_cnt = $tradeapi->query_one("SELECT SUM(amount) FROM js_auction_inventory WHERE  goods_idx='{$tradeapi->escape($goods_idx)}'");
$total_goods_cnt = $goods_info->nft_max_supply;
$my_goods_cnt = $tradeapi->query_one("SELECT SUM(amount) FROM js_auction_inventory WHERE  goods_idx='{$tradeapi->escape($goods_idx)}' AND userno='{$tradeapi->escape($userno)}' ");
// 보유수량보다 많이 판매 할 수 없음.
//if($my_goods_cnt < $sell_volume) {
//    $tradeapi->error('402', __('You cannot sell more than your holdings.'));
//}
// 분할 판매시 전체 수량을 보유한 회원만 분할 판매가능
if($split_volume && $total_goods_cnt!=$my_goods_cnt) {
    $tradeapi->error('403', __('Only members who have the full quantity can sell in split.'));
} else {
    // 분할 가능한 경우
}
// // 분할된 상품을 분할 판매할때는 전체 수량을 보유하고 있는지 확인.
// if($total_goods_cnt>1 && $split_volume>1) {  // 분할된 상품을 분할 판매할때
//     $tradeapi->error('401', __('A split product cannot be subdivided.').$total_goods_cnt.','.$split_volume);
// }
// 분할 판매시 묶음상품이면 불가처리
if($split_volume>1 && $goods_info->pack_info=='Y'){
    $tradeapi->error('404', __('Bundles cannot be split.'));
}


// 경매 수정용 경매번호 맞나?
if($auction_idx){

    // 전달 받은 경매번호 확인.
//    if($auction_idx != $goods_info->auction_idx){
//        $tradeapi->error('501', __('경매번호가 경매에 등록된 상품정보와 다릅니다.'));
//    }
//    // 내것인가?
//    if($userno != $goods_info->userno){
//        $tradeapi->error('502', __('보유 중인 상품이 아닙니다.'));
//    }
//    // 내 경매 맞나?
//    if($userno != $goods_info->creator_userno){
//        $tradeapi->error('503', __('경매번호 등록자가 아닙니다.'));
//    }
    // 경매 시작했으면 수정 불가.
    // if($goods_info->started=='Y'){
    //     $tradeapi->error('504', __('경매가 시작되어 수정할 수 없습니다.'));
    // }
    $bidders = $tradeapi->query_one("SELECT COUNT(*) FROM js_auction_apply WHERE auction_idx='{$tradeapi->escape($auction_idx)}' AND status='P' ");
    if($goods_info->started=='Y' && $bidders>0 ){
        $tradeapi->error('504', __('입찰자가 있어 수정할 수 없습니다.'));
    }
    // 경매 종료했으면 수정 불가.
    if($goods_info->finish=='Y'){
        $tradeapi->error('505', __('경매가 종료되어 수정할 수 없습니다.'). $goods_info->auction_idx.','.$goods_info->l_finish);
    }

    // 분할 정보 있으면 상품정보에 추가 및 보유 수량 증가/감소
    if($split_volume && $my_goods_cnt==$total_goods_cnt && $split_volume!=$total_goods_cnt) {
        
        // js_exchange_wallel 소유량 증가 처리중
        $priv_nft_max_supply = $tradeapi->query_one("SELECT nft_max_supply FROM js_auction_goods WHERE idx='{$tradeapi->escape($goods_idx)}' ");

        $tradeapi->query("UPDATE js_exchange_wallet_nft SET amount='{$tradeapi->escape($split_volume)}' WHERE userno='{$tradeapi->escape($userno)}' AND symbol='{$tradeapi->escape($goods_info->nft_symbol)}' AND tokenid='{$tradeapi->escape($goods_info->nft_id)}' "); // 1개를 분할하기때문에 1+split_volume -1 이라면 그냥 splite_volume으로 치환합니다.
        $seller_wallet = $tradeapi->query_fetch_object("SELECT * FROM js_exchange_wallet WHERE userno='{$tradeapi->escape($userno)}' AND symbol='{$tradeapi->escape($goods_info->nft_symbol)}' ");
        $new_amount = $split_volume - $priv_nft_max_supply;
        if($new_amount>0) {
            $tradeapi->query("UPDATE js_exchange_wallet SET confirmed=confirmed+'{$tradeapi->escape($new_amount)}' WHERE userno='{$tradeapi->escape($userno)}' AND symbol='{$tradeapi->escape($goods_info->nft_symbol)}' "); // 1개를 분할하기때문에 split_volume -1 한값을 confrimed에 더합니다.
            $tradeapi->query("INSERT INTO js_exchange_wallet_txn SET userno='{$tradeapi->escape($userno)}', symbol='{$tradeapi->escape($goods_info->nft_symbol)}', address='{$tradeapi->escape($seller_wallet->address)}', regdate=NOW(), txndate=NOW(), address_relative='', txn_type='GD', direction='I', nft_id='{$tradeapi->escape($goods_info->nft_id)}', amount='{$tradeapi->escape($new_amount)}', fee='0', tax='0', status='D', service_name='AUCTION', key_relative='{$tradeapi->escape($auction_idx)}', txn_method='COIN', app_no='".__APP_NO__."', msg=''  "); // 1개를 split_volume으로 늘리기때문에 new_amount만큼 추가한걸로 처리합니다.
        }
        if($new_amount<0) {
            $new_amount = abs($new_amount);
            $tradeapi->query("UPDATE js_exchange_wallet SET confirmed=confirmed-'{$tradeapi->escape($new_amount)}' WHERE userno='{$tradeapi->escape($userno)}' AND symbol='{$tradeapi->escape($goods_info->nft_symbol)}' "); // 1개를 분할하기때문에 split_volume -1 한값을 confrimed에 더합니다.
            $tradeapi->query("INSERT INTO js_exchange_wallet_txn SET userno='{$tradeapi->escape($userno)}', symbol='{$tradeapi->escape($goods_info->nft_symbol)}', address='{$tradeapi->escape($seller_wallet->address)}', regdate=NOW(), txndate=NOW(), address_relative='', txn_type='GD', direction='O', nft_id='{$tradeapi->escape($goods_info->nft_id)}', amount='{$tradeapi->escape($new_amount)}', fee='0', tax='0', status='D', service_name='AUCTION', key_relative='{$tradeapi->escape($auction_idx)}', txn_method='COIN', app_no='".__APP_NO__."', msg=''  "); // 1개를 split_volume으로 늘리기때문에 new_amount만큼 추가한걸로 처리합니다.
        }

        $tradeapi->query("UPDATE js_auction_inventory SET amount='{$tradeapi->escape($split_volume)}' WHERE goods_idx='{$tradeapi->escape($goods_idx)}' AND userno='{$tradeapi->escape($userno)}' ");
        $tradeapi->query("UPDATE js_auction_goods SET nft_max_supply='{$tradeapi->escape($split_volume)}' WHERE idx='{$tradeapi->escape($goods_idx)}' ");

        $sell_volume = $split_volume;
    }

    //
    // var_dump($sell_volume, $split_volume, $my_goods_cnt, $total_goods_cnt); exit; // 업데이트 안됨.

	$sql = "UPDATE js_auction_list SET mod_date=NOW() ";
    // if(isset($_REQUEST['goods_idx']) && $goods_idx!=$goods_info->goods_idx ) { $sql.= ",goods_idx='{$tradeapi->escape($goods_idx)}' "; }
    if(isset($_REQUEST['auction_title'])) { $sql.= ",auction_title='{$tradeapi->escape($auction_title)}' "; }
    if(isset($_REQUEST['start_date'])) { $sql.= ",start_date='{$tradeapi->escape($start_date)}' "; }
    if(isset($_REQUEST['end_date'])) { $sql.= ",end_date='{$tradeapi->escape($end_date)}' "; }
    if(isset($_REQUEST['start_price'])) { $sql.= ",start_price='{$tradeapi->escape($start_price)}' "; }
    if(isset($_REQUEST['sell_price'])) { $sql.= ",sell_price='{$tradeapi->escape($sell_price)}' "; }
    if(isset($_REQUEST['wish_price'])) { $sql.= ",wish_price='{$tradeapi->escape($wish_price)}' "; }
    if(isset($_REQUEST['unit_price'])) { $sql.= ",unit_price='{$tradeapi->escape($unit_price)}' "; }
    if(isset($_REQUEST['buy_now'])) { $sql.= ",buy_now='{$tradeapi->escape($buy_now)}' "; }

    if(isset($_REQUEST['title'])) { $sql.= ",title='{$tradeapi->escape($title)}' "; }
    if(isset($_REQUEST['sub_title'])) { $sql.= ",sub_title='{$tradeapi->escape($sub_title)}' "; }
    if(isset($_REQUEST['buy_now'])) { $sql.= ",buy_now='{$tradeapi->escape($buy_now)}' "; }
    if(isset($_REQUEST['buy_now'])) { $sql.= ",buy_now='{$tradeapi->escape($buy_now)}' "; }
    if(isset($_REQUEST['finish'])) { $sql.= ",finish='{$tradeapi->escape($finish)}' "; }

    if(isset($_REQUEST['main_pic'])) { $sql.= ",main_pic='{$tradeapi->escape($main_pic)}' "; }
    if(isset($_REQUEST['sub1_pic'])) { $sql.= ",sub1_pic='{$tradeapi->escape($sub1_pic)}' "; }
    if(isset($_REQUEST['sub2_pic'])) { $sql.= ",sub2_pic='{$tradeapi->escape($sub2_pic)}' "; }
    if(isset($_REQUEST['sub3_pic'])) { $sql.= ",sub3_pic='{$tradeapi->escape($sub3_pic)}' "; }
    if(isset($_REQUEST['sub4_pic'])) { $sql.= ",sub4_pic='{$tradeapi->escape($sub4_pic)}' "; }
    if(isset($_REQUEST['active'])) { $sql.= ",active='{$tradeapi->escape($active)}' "; }
    if(isset($_REQUEST['content'])) { $sql.= ",content='{$tradeapi->escape($content)}' "; }
    if(isset($_REQUEST['detail_contents'])) { $sql.= ",detail_contents='{$tradeapi->escape($detail_contents)}' "; }

    if(isset($_REQUEST['sell_volume'])) { $sql.= ",sell_volume='{$tradeapi->escape($sell_volume)}' "; }

    $sql.= "WHERE auction_idx='{$auction_idx}' ";
    // var_dump($sql, $_REQUEST['end_date'], $end_date, $goods_info->end_date, $end_date!=$goods_info->end_date);// exit;


    $result = $tradeapi->query($sql);

    // 상품 가격 변경하기
    if(isset($_REQUEST['start_price']) && $start_price!=$goods_info->start_price ) {
        // var_dump(); exit;
        $tradeapi->query("update js_auction_goods set price='{$tradeapi->escape($start_price)}' where idx='{$tradeapi->escape($goods_idx)}' ");
    }

} else { // 진행중인 경매가 없을때.

//    // 내것인가?
//    if($goods_info->userno != $userno){
//        $tradeapi->error('512', __('보유 중인 상품이 아니라서 경매를 등록하지 못했습니다.'));
//    }
//    // 진행중인 경매가 있는가?
//    if($goods_info->auction_idx){
//        $tradeapi->error('513', __('진행중인 경매가 있어 등록하지 못했습니다.'));
//    }
//    // 묶음 상품의 서브상품인가? 서브상품은 단일로 판매 못함.
//    if($goods_info->pack_info!='Y' && $goods_info->pack_info!='N'){
//        $tradeapi->error('514', __('묶음 판매중인 상품이라 등록하지 못했습니다.').$goods_info->pack_info);
//    }

    // 분할 정보 있으면 상품정보에 추가 및 보유 수량 증가/감소
    if($split_volume && $my_goods_cnt==$total_goods_cnt && $split_volume!=$total_goods_cnt) {

        // js_exchange_wallel 소유량 증가 처리중
        $priv_nft_max_supply = $tradeapi->query_one("SELECT nft_max_supply FROM js_auction_goods WHERE idx='{$tradeapi->escape($goods_idx)}' ");
        
        $tradeapi->query("UPDATE js_exchange_wallet_nft SET amount='{$tradeapi->escape($split_volume)}' WHERE userno='{$tradeapi->escape($userno)}' AND symbol='{$tradeapi->escape($goods_info->nft_symbol)}' AND tokenid='{$tradeapi->escape($goods_info->nft_id)}' "); // 1개를 분할하기때문에 1+split_volume -1 이라면 그냥 splite_volume으로 치환합니다.
        $seller_wallet = $tradeapi->query_fetch_object("SELECT * FROM js_exchange_wallet WHERE userno='{$tradeapi->escape($userno)}' AND symbol='{$tradeapi->escape($goods_info->nft_symbol)}' ");
        $new_amount = $split_volume - $priv_nft_max_supply;
        if($new_amount>0) {
            $tradeapi->query("UPDATE js_exchange_wallet SET confirmed=confirmed+'{$tradeapi->escape($new_amount)}' WHERE userno='{$tradeapi->escape($userno)}' AND symbol='{$tradeapi->escape($goods_info->nft_symbol)}' "); // 1개를 분할하기때문에 split_volume -1 한값을 confrimed에 더합니다.
            $tradeapi->query("INSERT INTO js_exchange_wallet_txn SET userno='{$tradeapi->escape($userno)}', symbol='{$tradeapi->escape($goods_info->nft_symbol)}', address='{$tradeapi->escape($seller_wallet->address)}', regdate=NOW(), txndate=NOW(), address_relative='', txn_type='GD', direction='I', nft_id='{$tradeapi->escape($goods_info->nft_id)}', amount='{$tradeapi->escape($new_amount)}', fee='0', tax='0', status='D', service_name='AUCTION', key_relative='{$tradeapi->escape($auction_idx)}', txn_method='COIN', app_no='".__APP_NO__."', msg=''  "); // 1개를 split_volume으로 늘리기때문에 new_amount만큼 추가한걸로 처리합니다.
        }
        if($new_amount<0) {
            $new_amount = abs($new_amount);
            $tradeapi->query("UPDATE js_exchange_wallet SET confirmed=confirmed-'{$tradeapi->escape($new_amount)}' WHERE userno='{$tradeapi->escape($userno)}' AND symbol='{$tradeapi->escape($goods_info->nft_symbol)}' "); // 1개를 분할하기때문에 split_volume -1 한값을 confrimed에 더합니다.
            $tradeapi->query("INSERT INTO js_exchange_wallet_txn SET userno='{$tradeapi->escape($userno)}', symbol='{$tradeapi->escape($goods_info->nft_symbol)}', address='{$tradeapi->escape($seller_wallet->address)}', regdate=NOW(), txndate=NOW(), address_relative='', txn_type='GD', direction='O', nft_id='{$tradeapi->escape($goods_info->nft_id)}', amount='{$tradeapi->escape($new_amount)}', fee='0', tax='0', status='D', service_name='AUCTION', key_relative='{$tradeapi->escape($auction_idx)}', txn_method='COIN', app_no='".__APP_NO__."', msg=''  "); // 1개를 split_volume으로 늘리기때문에 new_amount만큼 추가한걸로 처리합니다.
        }

        $tradeapi->query("UPDATE js_auction_inventory SET amount='{$tradeapi->escape($split_volume)}' WHERE goods_idx='{$tradeapi->escape($goods_idx)}' AND userno='{$tradeapi->escape($userno)}'  ");
        $tradeapi->query("UPDATE js_auction_goods SET nft_max_supply='{$tradeapi->escape($split_volume)}' WHERE idx='{$tradeapi->escape($goods_idx)}' ");
        
        $sell_volume = $split_volume;
    }

    //옥션 pk 생성 = type의 3글자 + 날짜 시분초ms까지
    $auction_idx = substr($tradeapi->escape($goods_info->goods_type), 0, 3).substr(date("ymdHisu"), 0, 13);

	$sql = "INSERT INTO js_auction_list SET 
            auction_idx='{$auction_idx}',
            goods_idx='{$tradeapi->escape($goods_idx)}',
            auction_title='{$tradeapi->escape($auction_title)}',
            sell_type='{$tradeapi->escape($sell_type)}',
            title='{$tradeapi->escape($title)}',
            sub_title='{$tradeapi->escape($sub_title)}',
            start_date='{$tradeapi->escape($start_date)}',
            end_date='{$tradeapi->escape($end_date)}',
            start_price='{$tradeapi->escape($start_price)}',
            sell_price='{$tradeapi->escape($sell_price)}',
            sell_volume='{$tradeapi->escape($sell_volume)}',
            wish_price='{$tradeapi->escape($wish_price)}',
            unit_price='{$tradeapi->escape($unit_price)}',
            creator_userno='{$tradeapi->escape($userno)}',
            buy_now='{$tradeapi->escape($buy_now)}',
            finish='N',
            main_pic='{$tradeapi->escape($main_pic)}',
            sub1_pic='{$tradeapi->escape($sub1_pic)}',
            sub2_pic='{$tradeapi->escape($sub2_pic)}',
            sub3_pic='{$tradeapi->escape($sub3_pic)}',
            sub4_pic='{$tradeapi->escape($sub4_pic)}',
            active='{$tradeapi->escape($active)}',
            content='{$tradeapi->escape($content)}',
            detail_contents='{$tradeapi->escape($detail_contents)}',
            reg_date=NOW() ";

    $result = $tradeapi->query($sql);
    if($result) {
        $tradeapi->query("UPDATE js_auction_goods SET price='{$tradeapi->escape($start_price)}' WHERE idx='{$tradeapi->escape($goods_idx)}' ");
    }

    // twitter
    // 사이트 설정값으로 사용시
    if($i==0 && $write_twitter && CONSUMER_KEY && CONSUMER_SECRET && ACCESS_TOKEN && ACCESS_TOKEN_SECRET) {
        $msg = '엔에프티엔(nft-n.com)에서 새로운 NFT을 확인하세요. '.$auction_title.' https://www.nft-n.com/detail.html?goods_idx='.$goods_idx;// .'&t='.time()
        $tradeapi->write_msg_twitter($msg);
    }

}

$tradeapi->success(array('auction_idx'=>$auction_idx));
