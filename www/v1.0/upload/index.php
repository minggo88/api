<?php
include dirname(__file__)."/../../lib/TradeApi.php";

/**
 * 파일 업로드 API Method
 */
// 로그인 세션 확인.
// $tradeapi->checkLogin();
$userno = $tradeapi->get_login_userno();

// validate parameters
// $file_class = checkFileClass(strtolower(checkEmpty($_REQUEST['file_class'], 'file class'))); // 코인
$storage_type = strtolower(setDefault($_REQUEST['storage_type'], 'aws_s3')); // 저장소 종류
$base64_file_data = setDefault(loadParam('base64_file_data'),''); // input file이 아니라  data:image/png;base64,AAAFBfj42Pj4.. 처럼 base64 데이터 소스를 전달 할때

// --------------------------------------------------------------------------- //

// base64 데이터 소스로 받은경우 (data:image/png;base64,AAAFBfj42Pj4...)
if($base64_file_data && empty($_FILES)) {
	// $base64_file_data = 'data:image/png;base64,AAAFBfj42Pj4';
	list($type, $file_data) = explode(';', $base64_file_data);
	$type = str_replace('data:','',$type);
	list(, $exc) = explode('/', $type);
	list(, $file_data)      = explode(',', $file_data);
	$file_data = base64_decode($file_data);

	$tmpdir = __SRF_DIR__.'/cache/upload';
	if(!file_exists($tmpdir)) { mkdir($tmpdir, 0777, true); }
	$tmpname = sha1(time().$base64_file_data).'.'.$exc;
	$tmpfile = $tmpdir.'/'.$tmpname;
	file_put_contents($tmpfile, $file_data);

	$_FILES['file_data'] = array(
		'name'=>$tmpname,
		'type'=>$type,
		'tmp_name'=>$tmpfile,
		'error'=>null,
		'size'=>filesize($tmpfile)
	);
}

// 마스터 디비 사용하도록 설정.
$tradeapi->set_db_link('master');

// file upload to s3
// var_dump($_FILES); exit;
// $files = $tradeapi->save_file_to_s3($_FILES['file_data']);
switch($storage_type) {
	case 'google_drive':
		$files = $tradeapi->save_file_to_google_drive($_FILES['file_data']);
		// https://drive.google.com/uc?export=view&id=1I7NL89ymCUvtC-smqIrDDzi49AoQ7SSc
	break;
	case 'aws_s3':
	case 'aws':
	default :
		$files = $tradeapi->save_file_to_s3($_FILES['file_data']);
		// https://smarttalk.s3.ap-northeast-2.amazonaws.com/tmp/202109/90d0d051f8551a81813c0fde52ea285ebb7210570057a1850276e5e4551781d5.png
	break;
}

if($tmpfile) unlink($tmpfile);

// response
$tradeapi->success($files);
