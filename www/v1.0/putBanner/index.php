<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

$tradeapi->set_logging(true);
$tradeapi->set_log_dir(__dir__.'/../../log/'.basename(__dir__).'/');
$tradeapi->set_log_name(basename(__file__));
$tradeapi->write_log("REQUEST: " . json_encode($_REQUEST));

/**
 * 배너 생성/수정
 */
// 로그인 세션 확인.
//$tradeapi->checkLogin();

$userid = $tradeapi->get_login_userid();
$userno = $tradeapi->get_member_info(2)->userno;


$idx    = setDefault($_REQUEST['idx'], '');                     //경매번호. 경매시작전 수정할때 사용

$title          = setDefault($_REQUEST['title'], '');           //배너 제목
$active         = setDefault($_REQUEST['active'], 'N');         //사용유무
$sortno         = setDefault($_REQUEST['sortno'], '');          //순서
$banner_url     = setDefault($_REQUEST['banner_url'], '');      //배너 링크주소
$text_1         = setDefault($_REQUEST['text_1'], '');          //문구
$text_2         = setDefault($_REQUEST['text_2'], '');          //문구
$img_banner     = setDefault($_REQUEST['img_banner'], '');               //상세내용

// 이미지 s3 정식폴더로 이동
$s3_check_param = array('img_banner');
foreach($s3_check_param as $param) {
    $file = $$param;
    if($file && strpos($file, '.s3.')!==false && strpos($file, '/tmp/')!==false) {
        $$param = $tradeapi->move_tmpfile_to_s3($file);
    }
}

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('master');

// 경매 수정용 경매번호 맞나?
if($idx){

    $sql = "UPDATE js_banner SET  ";

    if(isset($_REQUEST['title'])) { $sql.= "title='{$tradeapi->escape($title)}' "; }
    if(isset($_REQUEST['banner_url'])) { $sql.= ",banner_url='{$tradeapi->escape($banner_url)}' "; }
    if(isset($_REQUEST['active'])) { $sql.= ",active='{$tradeapi->escape($active)}' "; }
    if(isset($_REQUEST['sortno'])) { $sql.= ",sortno='{$tradeapi->escape($sortno)}' "; }
    if(isset($_REQUEST['text_1'])) { $sql.= ",text_1='{$tradeapi->escape($text_1)}' "; }
    if(isset($_REQUEST['text_2'])) { $sql.= ",text_2='{$tradeapi->escape($text_2)}' "; }
    if(isset($_REQUEST['img_banner'])) { $sql.= ",img_banner='{$tradeapi->escape($img_banner)}' "; }
    $sql.= "WHERE idx='{$idx}' ";
    // var_dump($sql, $_REQUEST['end_date'], $end_date, $goods_info->end_date, $end_date!=$goods_info->end_date);// exit;


    $result = $tradeapi->query($sql);


} else { // insert


    $sql = "INSERT INTO js_banner SET 
            title='{$title}',
            banner_url='{$tradeapi->escape($banner_url)}',
            active='{$tradeapi->escape($active)}',
            sortno='{$tradeapi->escape($sortno)}',
            text_1='{$tradeapi->escape($text_1)}',
            text_2='{$tradeapi->escape($text_2)}',
            img_banner='{$tradeapi->escape($img_banner)}',
            bannercode='',
            reg_date=NOW() ";

    $result = $tradeapi->query($sql);

}

if($result) {
    $tradeapi->success(array('idx'=>$idx));
}


