<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 로그인 세션 확인.
$tradeapi->checkLogin();
$userno = $tradeapi->get_login_userno();

$_REQUEST['userno'] = $userno;

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('master');


$key = $tradeapi->search_kkikdageo();

// 원본 데이터
$data = '1111';
$r = '생성성공';
// 암호화
$encryptedData = openssl_encrypt($data, 'AES-256-CBC', $key, 0, '1234567890123456');
try{
   // 저장
file_put_contents(dirname(__FILE__).'/../../np/sk.bin', $encryptedData);

// 이진 파일에서 암호화된 데이터 읽기
$encryptedData = file_get_contents(dirname(__FILE__).'/../../np/sk.bin');

// 복호화
$decryptedData = openssl_decrypt($encryptedData, 'AES-256-CBC', $key, 0, '1234567890123456');
$r = $r.$decryptedData;

} catch(Exception $e) {
   $r = '생성실패'.$e;
}



/***
 *  ----------------- 여기까지
 */

// get my member information
//$r = $tradeapi->save_member_info($_REQUEST);

// response
$tradeapi->success($r);

?>