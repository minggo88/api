<?php
ob_implicit_flush(true);
ignore_user_abort(1);
set_time_limit(0);

include dirname(__file__) . "/../../lib/TradeApi.php";

$tradeapi->set_logging(true);
$tradeapi->set_log_dir(__dir__.'/../../log/'.basename(__dir__).'/');
$tradeapi->set_log_name(basename(__file__));
$tradeapi->write_log("REQUEST: " . json_encode($_REQUEST));

/**
 * 경매 상품 생성/수정
 */
// 로그인 세션 확인. 
// 입력 값이 없으면 로그인 확인하는것으로 변경
$creator_userno      = setDefault($_REQUEST['creator_userno'], '');

if($creator_userno) {
    $user_info = $tradeapi->get_user_info($creator_userno);

    if(!$user_info || !$user_info->userno) {
        $tradeapi->error('200', '회원정보를 확인해주세요.');
    }
    $userid = $user_info->userid;
    $userno = $creator_userno;
} else {
    $tradeapi->checkLogin();
    $userid = $tradeapi->get_login_userid();
    $userno = $tradeapi->get_login_userno();
}


$goods_idx      = setDefault($_REQUEST['goods_idx'], '');                   //js_auction_goods.idx
$stock_number   = setDefault($_REQUEST['stock_number'], '');                          //재고번호
$title          = checkEmpty($_REQUEST['title'], 'title');                  //상품제목
$content        = checkEmpty($_REQUEST['content'], 'content');              //상품내용
$goods_type     = setDefault($_REQUEST['goods_type'], '');                  //상품종류(카테고리)
$main_pic       = setDefault($_REQUEST['main_pic'], '');                    //상품 메인이미지URL
$sub1_pic       = setDefault($_REQUEST['sub1_pic'], '');                      //상품 서브1이미지URL
$sub2_pic       = setDefault($_REQUEST['sub2_pic'], '');                      //상품 서브2이미지URL
$sub3_pic       = setDefault($_REQUEST['sub3_pic'], '');                      //상품 서브3이미지URL
$sub4_pic       = setDefault($_REQUEST['sub4_pic'], '');                      //상품 서브4이미지URL
$animation      = setDefault($_REQUEST['animation'], '');                     //상품 애니메이션이미지URL
$explicit_content = resetYN(setDefault($_REQUEST['explicit_content'], 'N'), 'Y');      //노골적(성인,폭력) 콘텐츠 여부. N:아님, Y:응

$nft_symbol      = setDefault($_REQUEST['nft_symbol'], 'NFTN');                  //NFT 토큰 심볼
// $nft_symbol      = setDefault($_REQUEST['nft_symbol'], 'NFTN');                  //NFT 토큰 심볼
$nft_blockchain  = setDefault($_REQUEST['nft_blockchain'], 'ETH');              //NFT 토큰 블록체인 이름  --> symbol로 변경. 이름은 join이 불편함.
$nft_id          = setDefault($_REQUEST['nft_id'], '');                         //NFT 토큰 아아디
$nft_unlockable_contents = setDefault($_REQUEST['nft_unlockable_contents'], '');//NFT 잠금 해제 가능한 콘텐츠 - 보유자만 볼 수 있는 내용
$nft_max_supply  = setDefault($_REQUEST['nft_max_supply'], '1');                //NFT 최대 발행량. 나중에 거래소 갈때 수정 가능. 참고자료.
$nft_buildable   = setDefault($_REQUEST['nft_buildable'], 'Y');                 //NFT 생성여부. 기본값 Y. NFT 준비가 완료된 후 생성시킵니다. 생성완료후 수정본은 metadata 파일 내용을 수정하면 됩니다.
$nft_file_type   = checkEmpty($_REQUEST['nft_file_type'], 'nft_file_type');     //NFT 파일종류. IMAGE:이미지파일, VIDEO:비디오파일, AUDIO:오디오파일, 3D:3D파일
$base_price      = checkNumber(setDefault($_REQUEST['base_price'], '0'));       //NFT 기본 가격.
$base_price     *= 1; // 불필요한 숫자 제거. 1.50000 -> 1.5
$royalty      = setDefault($_REQUEST['royalty'], '');       //NFT 기본 가격.
$goods_grade      = setDefault($_REQUEST['goods_grade'], '');       //상품 등급

if(strpos($royalty, '%')!==false) { // % 있으면
    checkNumber(str_replace('%','',$royalty));
    if(str_replace('%','',$royalty)>10) { // 10% 까지 로열티 입력 가능
        $tradeapi->error('200', __('Please enter a royalty of 10% or less.'));
    }
} else {
    checkNumber($royalty);
}
$price_symbol    = setDefault($_REQUEST['price_symbol'], 'KRW');                //NFT 기본 가격 심볼.( 기본값: ETH)
$minting_quantity    = setDefault($_REQUEST['minting_quantity'], '1');                //민팅 수량

$start_date    = setDefault($_REQUEST['start_date'], '');                      //경매 시작날짜
$end_date    = setDefault($_REQUEST['end_date'], '');                      //경매 종료날짜

// 가격 소숫점자릿수 수정(버림)
$price_currency_info = $tradeapi->query_fetch_object("SELECT base_coin, auction_manager_userno, display_decimals FROM js_exchange_currency ec LEFT JOIN js_auction_currency ac ON ec.symbol=ac.symbol WHERE ec.symbol='{$tradeapi->escape($price_symbol)}' ");
$base_price    = $base_price>0 ? real_number($base_price, $price_currency_info->display_decimals, 'floor') : $base_price;

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('master');

// goods_type 이 title로 전달 받으면 goods_type으로 바꿉니다. 없으면 에러.
$goods_type_db = $tradeapi->query_one("SELECT goods_type FROM js_auction_goods_type WHERE goods_type='{$tradeapi->escape($goods_type)}' ");
if(!$goods_type_db) {
    $goods_type_db = $tradeapi->query_one("SELECT goods_type FROM js_auction_goods_type WHERE title='{$tradeapi->escape($goods_type)}' ");
    if(!$goods_type_db) {
        $tradeapi->error('300', __('올바른 카테고리값을 전달해주세요.'));
    }
    $goods_type = $goods_type_db;
}

// 이미지 s3 정식폴더로 이동
$s3_check_param = array('main_pic', 'sub1_pic', 'sub2_pic', 'sub3_pic', 'sub4_pic', 'sub5_pic', 'sub6_pic', 'sub7_pic', 'sub8_pic', 'sub9_pic', 'sub10_pic', 'animation');
foreach($s3_check_param as $param) {
    $file = $$param;
    if($file && strpos($file, '.s3.')!==false && strpos($file, '/tmp/')!==false) {
        $$param = $tradeapi->move_tmpfile_to_s3($file);
    }
}
//"https://smarttalk.s3.ap-northeast-2.amazonaws.com/202109/a5272c02a4ece28f64af0e1e4a9e868216782983750af52adf45f57bf2707fa7.png"

// main_pic 없으면 sub이미지 중에서 하나 선택하기.
if(!$main_pic) {
    // $image_columns = array('sub1_pic', 'sub2_pic', 'sub3_pic', 'sub4_pic', 'sub5_pic', 'sub6_pic', 'sub7_pic', 'sub8_pic', 'sub9_pic', 'sub10_pic'); // 'main_pic', , 'animation'
    foreach($s3_check_param as $param) {
        if($$param && $param != 'main_pic') {
            $main_pic = $$param;
        }
    }
}

//제품 정보 가져오기
// $query = "SELECT g.idx FROM js_auction_inventory as i INNER JOIN js_auction_goods as g on g.idx=i.goods_idx WHERE i.goods_idx='{$tradeapi->escape($goods_idx)}'";
// $query = "SELECT g.*, i.userid FROM js_auction_goods as g LEFT JOIN js_auction_inventory as i on g.idx=i.goods_idx AND i.userno='{$tradeapi->escape($userno)}' WHERE g.idx='{$tradeapi->escape($goods_idx)}'";
if($goods_idx) {
    // $goods_idx = $tradeapi->query_one("SELECT idx FROM js_auction_goods WHERE idx='{$tradeapi->escape($goods_idx)}'");
    // 이미 있는 상품이면 소유자 인지 확인.
    // $goods_info = $tradeapi->query_fetch_object("SELECT g.*, i.userno FROM js_auction_goods as g LEFT JOIN js_auction_inventory as i on g.idx=i.goods_idx AND i.userno='{$tradeapi->escape($userno)}' WHERE g.idx='{$tradeapi->escape($goods_idx)}'");
    $goods_info = $tradeapi->query_fetch_object("SELECT * FROM js_auction_goods WHERE idx='{$tradeapi->escape($goods_idx)}'");
    if($goods_info->owner_userno != $userno){
        $tradeapi->error('500', __('보유 중인 상품이 아닙니다.'));
    }
    if(!$goods_info->idx){
        $tradeapi->error('501', __('상품정보가 없습니다.'));
    }

    // 로열티 수정권한 확인
    if($goods_info->creator_userno != $userno) {
        $tradeapi->error('503', __('로열티는 상품의 크리에이터만 수정할 수 있습니다.'));
    }

    // 상품정보 수정.update
	$sql = "UPDATE js_auction_goods SET idx='{$tradeapi->escape($goods_idx)}', ";
    if(isset($_REQUEST['stock_number']) && $stock_number!=$goods_info->stock_number ) { $sql.= "stock_number='{$tradeapi->escape($stock_number)}', "; }
    if(isset($_REQUEST['main_pic']) && $main_pic!=$goods_info->main_pic ) { $sql.= "main_pic='{$tradeapi->escape($main_pic)}', "; }
    if(isset($_REQUEST['sub1_pic']) && $sub1_pic!=$goods_info->sub1_pic ) { $sql.= "sub1_pic='{$tradeapi->escape($sub1_pic)}', "; }
    if(isset($_REQUEST['sub2_pic']) && $sub2_pic!=$goods_info->sub2_pic ) { $sql.= "sub2_pic='{$tradeapi->escape($sub2_pic)}', "; }
    if(isset($_REQUEST['sub3_pic']) && $sub3_pic!=$goods_info->sub3_pic ) { $sql.= "sub3_pic='{$tradeapi->escape($sub3_pic)}', "; }
    if(isset($_REQUEST['sub4_pic']) && $sub4_pic!=$goods_info->sub4_pic ) { $sql.= "sub4_pic='{$tradeapi->escape($sub4_pic)}', "; }
    if(isset($_REQUEST['animation']) && $animation!=$goods_info->animation ) { $sql.= "animation='{$tradeapi->escape($animation)}', "; }
    if(isset($_REQUEST['goods_type']) && $goods_type!=$goods_info->goods_type ) { $sql.= "goods_type='{$tradeapi->escape($goods_type)}', "; }
    if(isset($_REQUEST['title']) && $title!=$goods_info->title ) { $sql.= "title='{$tradeapi->escape($title)}', "; }
    if(isset($_REQUEST['content']) && $content!=$goods_info->content ) { $sql.= "content='{$tradeapi->escape($content)}', "; }
    if(isset($_REQUEST['explicit_content']) && $explicit_content!=$goods_info->explicit_content ) { $sql.= "explicit_content='{$tradeapi->escape($explicit_content)}', "; }
    if(isset($_REQUEST['nft_symbol']) && $nft_symbol!=$goods_info->nft_symbol ) { $sql.= "nft_symbol='{$tradeapi->escape($nft_symbol)}', "; }
    if(isset($_REQUEST['nft_blockchain']) && $nft_blockchain!=$goods_info->nft_blockchain ) { $sql.= "nft_blockchain='{$tradeapi->escape($nft_blockchain)}', "; }
    if(isset($_REQUEST['nft_id']) && $nft_id!=$goods_info->nft_id ) { $sql.= "nft_id='{$tradeapi->escape($nft_id)}', "; }
    if(isset($_REQUEST['nft_unlockable_contents']) && $nft_unlockable_contents!=$goods_info->nft_unlockable_contents ) { $sql.= "nft_unlockable_contents='{$tradeapi->escape($nft_unlockable_contents)}', "; }
    if(isset($_REQUEST['nft_max_supply']) && $nft_max_supply!=$goods_info->nft_max_supply ) { $sql.= "nft_max_supply='{$tradeapi->escape($nft_max_supply)}', "; }
    if(isset($_REQUEST['nft_buildable']) && $nft_buildable!=$goods_info->nft_buildable ) { $sql.= "nft_buildable='{$tradeapi->escape($nft_buildable)}', "; }
    if(isset($_REQUEST['nft_file_type']) && $nft_file_type!=$goods_info->nft_file_type ) { $sql.= "nft_file_type='{$tradeapi->escape($nft_file_type)}', "; }
    if(isset($_REQUEST['base_price']) && $base_price!=$goods_info->base_price ) { $sql.= "base_price='{$tradeapi->escape($base_price)}', "; }
    if(isset($_REQUEST['royalty']) && $royalty!=$goods_info->royalty ) { $sql.= "royalty='{$tradeapi->escape($royalty)}', "; }
    if(isset($_REQUEST['goods_grade']) && $goods_grade!=$goods_info->goods_grade ) { $sql.= "goods_grade='{$tradeapi->escape($goods_grade)}', "; }
    if(isset($_REQUEST['price_symbol']) && $price_symbol!=$goods_info->price_symbol ) { $sql.= "price_symbol='{$tradeapi->escape($price_symbol)}', "; }
    $sql.= "mod_date=NOW() ";
    $sql.= "WHERE idx='{$tradeapi->escape($goods_idx)}' AND owner_userno='{$tradeapi->escape($userno)}' ";
    // var_dump($sql, $goods_info->main_pic, $main_pic, $goods_info->explicit_content, $explicit_content); exit;
    $result = $tradeapi->query($sql);

    if($result) {
        // 이전이미지 삭제
        if(!$goods_info->nft_id) {// - nft_id(토큰아이디)가 없을때만 이미지 수정할 수 있음.
            $auction_idx = $tradeapi->query_one("SELECT auction_idx FROM js_auction_list WHERE goods_idx='{$tradeapi->escape($goods_idx)}' AND NOW()<start_date ");
            if(!$auction_idx) {// - 경매 진행중인것이 없어야 수정할 수 있음.
                if(isset($_REQUEST['main_pic']) && $main_pic!=$goods_info->main_pic && $goods_info->main_pic ) { $tradeapi->delete_file_to_s3($goods_info->main_pic); }
                if(isset($_REQUEST['sub1_pic']) && $sub1_pic!=$goods_info->sub1_pic && $goods_info->sub1_pic ) { $tradeapi->delete_file_to_s3($goods_info->sub1_pic); }
                if(isset($_REQUEST['sub2_pic']) && $sub2_pic!=$goods_info->sub2_pic && $goods_info->sub2_pic ) { $tradeapi->delete_file_to_s3($goods_info->sub2_pic); }
                if(isset($_REQUEST['sub3_pic']) && $sub3_pic!=$goods_info->sub3_pic && $goods_info->sub3_pic ) { $tradeapi->delete_file_to_s3($goods_info->sub3_pic); }
                if(isset($_REQUEST['sub4_pic']) && $sub4_pic!=$goods_info->sub4_pic && $goods_info->sub4_pic ) { $tradeapi->delete_file_to_s3($goods_info->sub4_pic); }
                if(isset($_REQUEST['animation']) && $animation!=$goods_info->animation && $goods_info->animation ) { $tradeapi->delete_file_to_s3($goods_info->animation); }
            }
        }
    }

    // 매타정보 수정 
    $tradeapi->save_goods_meta_data($goods_idx, $_REQUEST);

    // 리턴값 설정
    $default_goods_idx = $goods_idx;


} else {

    // main_pic 중복 확인.
    $duplicate_goods_idx = $tradeapi->query_one("SELECT idx FROM js_auction_goods WHERE main_pic='{$tradeapi->escape($main_pic)}'");
//    if($duplicate_goods_idx) {
//        $tradeapi->error('502', __('메인이미지가 이미 등록된 상품입니다.'));
//    }

    // 생성지갑주소 추출 및 확인
    $currency = $tradeapi->query_fetch_object("SELECT base_coin, auction_manager_userno FROM js_exchange_currency ec LEFT JOIN js_auction_currency ac ON ec.symbol=ac.symbol WHERE ec.symbol='{$tradeapi->escape($nft_symbol)}' ");
    if(!$currency) {
        $tradeapi->write_log( "nft_symbol의 정보가 없습니다. nft_symbol: {$nft_symbol}");
        $tradeapi->error('504', "nft_symbol의 정보가 없습니다.");
    }
    $manager_userno = $currency->auction_manager_userno;
    $manager_address = $tradeapi->query_one("SELECT address FROM js_exchange_wallet WHERE userno='{$tradeapi->escape($manager_userno)}' AND symbol='{$tradeapi->escape($nft_symbol)}'");
    if(!$manager_address) {
        $address = $tradeapi->create_wallet($manager_userno, $nft_symbol, $goods_grade);
        if(!$address) {
            $tradeapi->write_log( "manager_address가 없어 생성하려 했지만 실패했습니다. manager_address: {$manager_address}, manager_userno: {$manager_userno}, item->idx: {$item->idx}");
            $tradeapi->error('505', "manager_address가 없어 생성하려 했지만 실패했습니다. manager_address: {$manager_address}, manager_userno: {$manager_userno}, item->idx: {$item->idx}");
        }
        $tradeapi->save_wallet($manager_userno, $nft_symbol, $address, 0, $goods_grade);
        $base_coin_symbol = $currency->base_coin;
        if($base_coin_symbol) {
            $tradeapi->save_wallet($manager_userno, $base_coin_symbol, $address, 0, $goods_grade);
        }
    }



    $write_twitter = $tradeapi->query_one("SELECT bool_write_twitter FROM js_config_auction WHERE code='{$tradeapi->escape($tradeapi->get_site_code())}' ");

    for($i=0; $i<=$minting_quantity; $i++) {

        $goods_idx = $tradeapi->gen_id();
        // 상품정보 추가. insert
        if($i==0) {
            $pack_goods_idx = $goods_idx;
        }

        // 매타정보 추가
        $tradeapi->save_goods_meta_data($goods_idx, $_REQUEST);
        // 게임용 아이템정보(attributes) 추가 - 불필요하여 제거. js_auction_goods_meta에 저장함.
        // token uri 생성(토큰정보url) 시작
        /* NFT 생성용 nodejs 소스에서 작업하도록 빈값으로 둡니다.
        // category name
        $category_name = $tradeapi->query_one("SELECT title FROM js_auction_goods_type WHERE goods_type='{$tradeapi->escape($goods_type)}'");
        // get item meta info - 게임속성 등의 아이템 속성값
        // $attributes = $tradeapi->query_list_object("SELECT meta_key trait_type, meta_val `value`, IF(meta_val REGEXP '[^0-9%.\-\: ]', 'string', IF(meta_val REGEXP '[%]', 'boost_percentage', IF(meta_val REGEXP '[^0-9.]', 'date', 'number'))) display_type FROM js_auction_goods_meta WHERE goods_idx='{$tradeapi->escape($goods_idx)}'");
        $attributes = NULL; // 불필요하여 제거
        // var_dump($attributes, "SELECT meta_key, meta_val FROM js_auction_goods_meta WHERE goods_idx='{$tradeapi->escape($item->idx)}'");
        $metadata = array(
            'name'=>$title,
            'description'=>$content,
            'goods_idx'=>$goods_idx,
            'category'=>$category_name,
            'image'=>$main_pic,
            'animation_url'=>$animation,
            'symbol'=>$nft_symbol,
            // 'contract_address'=>$item->nft_symbol,
            'token_id'=>$goods_idx,
            'attributes'=>$attributes
        );
        // var_dump($metadata);
        $metadata_file = __DIR__.'/../../cache/metadata/'.$item->idx.'_'.time().'.json';
        if(!file_exists(dirname($metadata_file))) { mkdir(dirname($metadata_file), 0777, true);}
        $r = file_put_contents($metadata_file, json_encode($metadata));
        if(!$r) {
            $tradeapi->write_log( "metadata_file을 생성하지 못했습니다. metadata_file: ".$metadata_file );
            exit;
        }
        // metadata 업로드
        $files['name'] = basename($metadata_file);
        $files['type'] = 'application/json';
        $files['tmp_name'] = $metadata_file;
        $files['error'] = '';
        $files['size'] = filesize($metadata_file);
        $tmpurl = $tradeapi->save_file_to_s3($files);
        $nft_tokenuri = $tradeapi->move_tmpfile_to_s3($tmpurl[0]['url']);
        // 구글드라이브 파일은 외부 사이트에서 ajax로 불러오지 못하기때문에 json 파일을 aws s3로 업로드 합니다.
        // $tmpurl = $tradeapi->save_file_to_google_drive($files);
        // $nft_tokenuri = $tmpurl[0]['url'];
        if(!$nft_tokenuri) {
            $tradeapi->write_log( "metadata_file을 업로드하지 못했습니다. nft_tokenuri: ".$nft_tokenuri.", files:".print_r($files, true).", tmpurl:".print_r($tmpurl, true) );
            exit;
        }
        $r = $tradeapi->query("UPDATE js_auction_goods SET nft_tokenuri='{$tradeapi->escape($nft_tokenuri)}' WHERE idx='{$tradeapi->escape($item->idx)}'");
        if(!$r) {
            $tradeapi->write_log( "nft_tokenuri를 DB에 저장하지 못했습니다. nft_tokenuri: ".$nft_tokenuri );
            $tradeapi->delete_file_to_s3($nft_tokenuri);
            // $tradeapi->delete_google_drive_by_url($nft_tokenuri);
            exit;
        }
        $item->nft_tokenuri = $nft_tokenuri;
        if(file_exists($metadata_file)) unlink($metadata_file);
        */
        // token uri 생성(토큰정보url) 끝


        // 멀티민팅일경우 이름에 번호 추가
        if($minting_quantity>1) {
            if($i==0) {
                $save_title = $title;
                $pack_info = 'Y'; // 묶음상품(부모)
                $nft_id = 'PACK'; // 묵음상품은 nft_id 생성 안함.
            } else {
                $save_title = "[{$i}/{$minting_quantity}] {$title}";
                $pack_info = $pack_goods_idx; // $i==0 일때 생성됩니다. 묶음상품(자식)
                $nft_id = $goods_idx;// 빈값에서 상품코드로 변경 '';
            }
        } else {
            $save_title = $title;
            $pack_info = 'N'; // 일반 단일 상품
            $nft_id = $goods_idx;// 빈값에서 상품코드로 변경 '';
        }

        // var_dump($goods_idx); exit;
        $sql = "INSERT INTO js_auction_goods SET idx='{$tradeapi->escape($goods_idx)}', stock_number='{$tradeapi->escape($stock_number)}', pack_info='{$tradeapi->escape($pack_info)}', main_pic='{$tradeapi->escape($main_pic)}', sub1_pic='{$tradeapi->escape($sub1_pic)}', sub2_pic='{$tradeapi->escape($sub2_pic)}', sub3_pic='{$tradeapi->escape($sub3_pic)}', sub4_pic='{$tradeapi->escape($sub4_pic)}', animation='{$tradeapi->escape($animation)}', goods_type='{$tradeapi->escape($goods_type)}', title='{$tradeapi->escape($save_title)}', content='{$tradeapi->escape($content)}', explicit_content='{$tradeapi->escape($explicit_content)}', nft_symbol='{$tradeapi->escape($nft_symbol)}', nft_blockchain='{$tradeapi->escape($nft_blockchain)}', nft_id='{$tradeapi->escape($nft_id)}', nft_unlockable_contents='{$tradeapi->escape($nft_unlockable_contents)}', nft_max_supply='{$tradeapi->escape($nft_max_supply)}', nft_buildable='{$tradeapi->escape($nft_buildable)}', nft_file_type='{$tradeapi->escape($nft_file_type)}', price='{$tradeapi->escape($base_price)}', base_price='{$tradeapi->escape($base_price)}', royalty='{$tradeapi->escape($royalty)}', goods_grade='{$tradeapi->escape($goods_grade)}', price_symbol='{$tradeapi->escape($price_symbol)}', creator_userno='{$tradeapi->escape($userno)}', owner_userno='{$tradeapi->escape($userno)}', reg_date=NOW() ";
        $result = $tradeapi->query($sql);

        // 인벤토리에 추가
        $result = $tradeapi->query("INSERT INTO js_auction_inventory SET userno='{$tradeapi->escape($userno)}', goods_idx='{$tradeapi->escape($goods_idx)}', userid='{$tradeapi->escape($userid)}', buy_price='0', buy_auction_idx='', reg_date=NOW() ");

        // 소유자 / 생성자 회원번호
        $creator_userno = $userno;
        $owner_userno = $userno;
        $owner_address = $tradeapi->query_one("SELECT address FROM js_exchange_wallet WHERE userno='{$tradeapi->escape($owner_userno)}' AND symbol='{$tradeapi->escape($nft_symbol)}'");
        
        // 임시로 민트 된것으로 로그 작성
        $microtime = $tradeapi->gen_microtime();
        $tradeapi->query("INSERT INTO `js_auction_txn` SET reg_time='{$tradeapi->escape($microtime)}', goods_idx='{$tradeapi->escape($goods_idx)}', symbol='{$tradeapi->escape($nft_symbol)}', tokenid='{$tradeapi->escape($goods_idx)}', txnid='', status='D', txn_type='M', sender_address='{$tradeapi->escape($manager_address)}', receiver_address='{$tradeapi->escape($owner_address)}', price_symbol='{$tradeapi->escape($price_symbol)}', price='{$tradeapi->escape($base_price)}', amount='1', fee='', tax='', message='', check_time='', sender_userno='{$tradeapi->escape($manager_userno)}', receiver_userno='{$tradeapi->escape($creator_userno)}', shop_id='', order_id='', relation_data=''   ");

        // js_exchange_wallet 에 저장? - 민트 되지 않아서 지갑에는 넣지 않는다? 블록체인 데이터만 사용합니다.
        $tradeapi->query("UPDATE `js_exchange_wallet` SET confirmed=confirmed+'{$tradeapi->escape($nft_max_supply)}' WHERE userno='{$tradeapi->escape($owner_userno)}' AND symbol='{$tradeapi->escape($nft_symbol)}' ");
        $tradeapi->query("INSERT INTO js_exchange_wallet_txn SET userno='{$tradeapi->escape($owner_userno)}', symbol='{$tradeapi->escape($nft_symbol)}', address='{$tradeapi->escape($owner_address)}', regdate=NOW(), txndate=NOW(), address_relative='{$tradeapi->escape($manager_address)}', txn_type='MI', direction='I', nft_id='{$tradeapi->escape($goods_idx)}', amount='1', fee='0', tax='0', status='D', service_name='AUCTION', key_relative='{$tradeapi->escape($goods_idx)}', txn_method='COIN', app_no='".__APP_NO__."', msg=''  ");
        // js_exchange_wallet_nft 에 저장?  - 민트 되지 않아서 지갑에는 넣지 않는다? 블록체인 데이터만 사용합니다.
        $tradeapi->query("INSERT INTO `js_exchange_wallet_nft` SET symbol='{$tradeapi->escape($nft_symbol)}', tokenid='{$tradeapi->escape($goods_idx)}', userno='{$tradeapi->escape($owner_userno)}', amount='{$tradeapi->escape($nft_max_supply)}', reg_date=NOW(), mode_date=NULL");

        if($minting_quantity<2) {
            break; // 1개 만드는경우 반복 중단.
        }

    }

    $default_goods_idx = $minting_quantity>1 ? $pack_goods_idx : $goods_idx;

    // 경매 등록
    if($start_date && $end_date) {

        //옥션 pk 생성 = type의 3글자 + 날짜 시분초ms까지
        $auction_idx = substr($tradeapi->escape($goods_type), 0, 3).substr(date("ymdHisu"), 0, 13);

        // 바로구매로 등록
        $sql = "INSERT INTO js_auction_list SET auction_idx='{$auction_idx}', goods_idx='{$tradeapi->escape($default_goods_idx)}', auction_title='{$tradeapi->escape($title)}', start_date='{$tradeapi->escape($start_date)}', end_date='{$tradeapi->escape($end_date)}', start_price='{$tradeapi->escape($base_price)}', sell_price='0', wish_price='{$tradeapi->escape($base_price)}', unit_price='0', creator_userno='{$tradeapi->escape($userno)}', buy_now='Y',  finish='N', reg_date=NOW() ";
        $tradeapi->query($sql);

        // twitter
        // 사이트 설정값으로 사용시
        if($i==0 && $write_twitter && CONSUMER_KEY && CONSUMER_SECRET && ACCESS_TOKEN && ACCESS_TOKEN_SECRET) {
            $msg = '엔에프티엔(nft-n.com)에서 새로운 NFT을 확인하세요. '.$title.' https://www.nft-n.com/detail.html?goods_idx='.$default_goods_idx;// .'&t='.time()
            $tradeapi->write_msg_twitter($msg);
        }

    }

}

$tradeapi->success(array('goods_idx'=> $default_goods_idx));
