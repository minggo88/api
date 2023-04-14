<?php
/**
 * NFT 전송
 *
 */
include dirname(__file__) . "/../../lib/ExchangeApi.php";

$exchangeapi->set_logging(true);
// $exchangeapi->set_log_dir(__dir__.'/');
// if(__API_RUNMODE__=='live') {
	$exchangeapi->set_log_dir($exchangeapi->log_dir.'/'.basename(__dir__).'/');
// }
$exchangeapi->set_log_name('');
$exchangeapi->write_log("REQUEST: " . json_encode($_REQUEST));

// 로그인 세션 확인.
$exchangeapi->checkLogin();
$userno = $exchangeapi->get_login_userno();
$userid = $exchangeapi->get_login_userid();
$user_info = $exchangeapi->get_member_info_by_userid($userid);

// validate parameters
$symbol = checkSymbol(strtoupper(setDefault(loadParam('symbol'), ''))); // 코인
$receiver_address = checkEmpty(loadParam('receiver_address'), 'receiver address'); // 수취인 주소
$nft_id = checkEmpty(loadParam('nft_id'));// NFT아이디
$amount = setDefault(loadParam('amount'), '1');// 송금량
$pin = checkEmpty(loadParam('pin')); // 계좌 송금 비번.
// $otppw = checkEmpty(loadParam('otppw')); // 계좌 송금 비번.
$msg = setDefault(loadParam('msg'), '');// 보내는 메시지

// --------------------------------------------------------------------------- //

// pin 번호 확인.
if($user_info->userpw!=md5($pin)) {
    $exchangeapi->error('025',__('Please enter the correct PIN number.'));
}

// otp google
// if($user_info->otpkey) {
// 	// include dirname(__file__) . "/../api/lib/GoogleAuthenticator.php";
// 	include dirname(__file__) . "/../../lib/GoogleAuthenticator.php";
// 	$ga = new PHPGangsta_GoogleAuthenticator();
// 	$c = $ga->getCode($user_info->otpkey);
// 	$otp_check = $c===$otppw;
// 	if(!$otp_check) {
// 		$exchangeapi->error('026',__('OTP Key를 다시입력해주세요.'));
// 	}
// } else {
// 	$exchangeapi->error('027',__('OTP를 등록해주세요.'));
// }

// 자동 잠금
// 1분에 10번 이상의 Send 요청이 발생한 경우. 자동 전송 차단.
// if( $exchangeapi->check_wallet_autolock($userno, $symbol) ) {
// 	$exchangeapi->error('057',__('Too many send occurred in a short time, and the send function was automatically locked. To unlock, contact administrator.'));
// }

// 로그인 사용자 지갑정보 가져오기.
$sender_wallet = $exchangeapi->get_wallet($userno, $symbol);
$sender_wallet = $sender_wallet[0];
if(!$sender_wallet->address) {
	$exchangeapi->error('048',__('You do not have a wallet.').' '.__('Please create a wallet.'));
}
// check locked
if($sender_wallet->locked != 'N') {
	$exchangeapi->error('048',__('Your wallet is locked and cannot be send.'));
}

// 토큰정보
// 토큰 소유자 맞는지 확인.
// 작업중================================================================


// 화폐정보
$currency = $exchangeapi->query_fetch_object("select * from js_exchange_currency where symbol='{$exchangeapi->escape($symbol)}' ");

// 받는 사람 지갑 주소 확인 없으면 사용자가 작성한 값을 사용.
// 지갑이름이 아니라 지갑주소로 들어 오는 경우인지 확인. 즉, $receiver_walletname으로 지갑 정보 조회
$receiver_wallet = $exchangeapi->get_wallet_by_address($receiver_address, $symbol);
if(!$receiver_wallet || !$receiver_wallet->userno) {
	// 받는사람을 그냥 전화번호로 전달 받은경우... 확인
	$receiver_userno = $exchangeapi->query_one("select userno from js_member where mobile='{$exchangeapi->escape($receiver_address)}' ");
	$receiver_wallet = $exchangeapi->query_fetch_object("select * from js_exchange_wallet where userno='{$exchangeapi->escape($receiver_userno)}' and symbol='{$exchangeapi->escape($symbol)}'");
	// 받는사람의 회원번호가 있는데... 지갑이 없으면 지갑을 만들어준다.
	if($receiver_userno && !$receiver_wallet) {
		$address = $exchangeapi->create_wallet($receiver_userno, $symbol);
		if(! $address) {
			$coind = $exchangeapi->load_coind($symbol);
			$errmsg = $coind->getError();
			$exchangeapi->error('014',__('수신자의 지갑이 없어 생성하려 했으나 생성하지 못했습니다.').$errmsg);
		}
		$exchangeapi->save_wallet($receiver_userno, $symbol, $address);
		$receiver_wallet = $exchangeapi->query_fetch_object("select * from js_exchange_wallet where userno='{$exchangeapi->escape($receiver_userno)}' and symbol='{$exchangeapi->escape($symbol)}'"); // 받는사람 지갑을 생성후 변수에 정보 안담아서 전달안되는 버그 수정.
	}

	// 암호화폐는 외부 지갑으로 보낼 수 있어서 여기 없을 수 있음.
	// 암호화폐가 아닌것은 PAY(내부 지갑전용)라서 외부로 보낼수 없음.
	if($currency->crypto_currency=='N' && (!$receiver_wallet || !$receiver_wallet->userno)) {
		$exchangeapi->error('050',__('There is no receiver wallet.').' '.__('Please enter a valid receiver address.'));
	}
}
$receiver_info = $exchangeapi->get_member_info($receiver_wallet->userno);
// var_dump($userno, $symbol, $sender_wallet, $receiver_wallet); exit;

// 본인에게 보내는 건지 주소 확인
if($receiver_wallet->userno == $userno) {
	$exchangeapi->error('030', '다른 회원님에게 보내주세요.');
}

// 외부 거래는 최소 전송 금액 제약을 둔다. 내부거래는 제한없음.
if( !$receiver_wallet && !$receiver_wallet->userno && $currency->out_min_volume >= 1 && $currency->out_min_volume > $amount) {
	if($_SERVER['REMOTE_ADDR']!='61.74.240.65') { // 회사에서 테스트할때 사용
		$exchangeapi->error('031', '외부로 송금시에는 최소 '.$currency->out_min_volume*1 .$currency->symbol.'이상으로 전송해주세요');
	}
}
if( !$receiver_wallet && !$receiver_wallet->userno && $currency->out_max_volume >= 1 && $currency->out_max_volume < $amount) {
	$exchangeapi->error('032', '외부로 송금시에는 최대 '.$currency->out_min_volume*1 .$currency->symbol.'미만으로 전송해주세요');
}
// $exchangeapi->error('030', 'test');exit;

// 더블클릭 막기 - 실수로 동일한 요청(발송계좌, 금액 동일)이 5초이내로 온것이 있는지 확인. 있으면 해당건은 작업 중단.
$duplicated = $exchangeapi->check_duplicated_transaction($sender_wallet->userno, $amount);
if($duplicated) {
	$exchangeapi->error('063', __('We blocked the same amount transfer in a short period of time to prevent duplicate shipments. Please send it after a while to send the same amount.'));
}

// 출금 신청.
// $txnid = $exchangeapi->send_coin ($symbol, $sender_wallet->address, $sender_wallet->account, $receiver_address, $amount, $fee, $msg, $sender_wallet->walletkey);
$txnid = ''; // 백그라운드로 처리.

// 수수료 조회
// UKRW 는 수수료가 미발생하기때문에 0으로 처리하지만, BTC 같은경우 수수료가 발생해서 send_coin을 실행후 수수료 계산을 해야 합니다.
// 내부 외부 구분없이 수수료 적용.
$fee = $exchangeapi->cal_fee($symbol, 'withdraw', $amount); //-> 외부 전송시
// $fee = $exchangeapi->cal_fee($symbol, 'internal', $amount); -> 내부 전송시 - 미정의항목
// 내부 전송시 수수료와 외부 전송시 수수료를 구분해서 처리해야 함. 구조상 외부 전송은 없기때문에 내부 수수료만 생각함. 일단 0으로 처리함.
// $fee = 0;
// 외부거래이고 화폐에 출금 수수료가 있으면 수수료 반영함.
// if(!$receiver_wallet) {
// 	if($currency->fee_out) {
// 		$fee = $currency->fee_out*1; //
// 	}
// }

// 세금
// $tax = $exchangeapi->cal_tax($symbol, 'withdraw', $amount); -> 외부 전송시
// $tax = $exchangeapi->cal_tax($symbol, 'internal', $amount); -> 내부 전송시 - 미정의항목
// 내부 전송시 수수료와 외부 전송시 수수료를 구분해서 처리해야 함. 구조상 외부 전송은 없기때문에 내부 수수료만 생각함. 일단 0으로 처리함.
$tax = 0;
if($currency->tax_out_ratio) {
	$tax = round($amount * $currency->tax_out_ratio, $currency->display_decimals)*1 ; //
}

// 보내는 금액
// $send_amount = $amount;
$send_amount = $amount + $fee + $tax; // 송금자에게서 수수료 차감
// 받는 금액 - 수수료나 세금을 제하고 받을 금액입니다. 내부거래는 보내는금액과 같습니다.
// $receive_amount = $amount - $fee - $tax; // 수신자에게 수수료 차감.
$receive_amount = $amount;

// 송금 가능 금액 다시 확인
if($sendable_amount < $send_amount ) {
	$exchangeapi->error('050',__('There is not enough balance to send.'));
}


// 상태값 : 외부 송금은 O(준비중), 내부송금은 D(종료)
$status = 'D';
if(!$receiver_wallet) {
	$status = 'O';
}

// var_dump($fee, $tax, $amount, $receive_amount, $sender_wallet, $receiver_wallet); exit;

// DB 트랜젝션 시작
$exchangeapi->transaction_start();


// 발송자 처리
// 트랜젝션에 저장.
$r = $exchangeapi->add_wallet_txn ($userno, $sender_wallet->address, $symbol, $receiver_address, 'S', 'O', $amount, $fee, $tax, $status, '', date('Y-m-d H:i:s'), $msg); // DB 처리라 상태는 완료로 처리함. ,$payment_info->method는 카드결제 연동시 사용하기.
$txnid = $exchangeapi->_recently_query['last_insert_id'];
if(!$r || !$txnid) {
	$exchangeapi->error('005',__('Failed to send.').' '.__('A system error has occurred.'));
}
// 잔액 반영
$exchangeapi->del_wallet($userno, $symbol, $send_amount);


// 수신자 처리.
if($receiver_wallet) {
	// 트랜젝션 저장.
	$r = $exchangeapi->add_wallet_txn ($receiver_wallet->userno, $receiver_wallet->address, $symbol, $sender_wallet->address, 'S', 'I', $receive_amount, $fee, $tax, $status, $txnid, date('Y-m-d H:i:s'), $msg);// DB 처리라 상태는 완료로 처리함. ,$payment_info->method는 카드결제 연동시 사용하기.
	if(!$r || !$txnid) {
		$exchangeapi->error('005',__('Failed to send.').' '.__('A system error has occurred.'));
	}
	// 잔액 반영
	$exchangeapi->add_wallet($receiver_wallet->userno, $symbol, $receive_amount); // 받는 사람의 지갑번호가 있는경우만 받는사람 지갑에 잔액 추가. 없는 경우 외부 지갑이라 패스.
// } else {
// 	$exchangeapi->error('006',__('Failed to send.')); // 외부 출금 차단 ... OTP 추가시 해제
}


// 수수료 지급 - 결제사마다 수수료는 다름. 전달 받은 수수료를 그대로 차감해 전달함.
if($fee>0) {
    // 결제 수수료 지갑 정보 추출 - 전송수수료를 관리 지갑에 전송합니다.(수수료 내역 확인용 DB 처리입니다.)
    $fee_wallet_info = $exchangeapi->get_row_wallet($currency->fee_save_userno, $symbol);
    if(!$fee_wallet_info->address) {
        $exchangeapi->error('050',__('There is no fee wallet.').' '.str_replace('{symbol}', $symbol, __('Please create a fee wallet for {symbol}.')));
    } else {
		// 트랜젝션 저장. txn_type F:수수료
		$exchangeapi->add_wallet_txn ($fee_wallet_info->userno, $fee_wallet_info->address, $symbol, $sender_wallet->address, 'F', 'I', $fee, 0, 0, 'D', $txnid, date('Y-m-d H:i:s'),'');// DB 처리라 상태는 완료로 처리함.
		// 잔액 반영
		$exchangeapi->add_wallet($fee_wallet_info->userno, $symbol, $fee); // 받는 사람의 지갑번호가 있는경우만 받는사람 지갑에 잔액 추가. 없는 경우 외부 지갑이라 패스.
	}

	// old
    // // 결제 수수료 지갑 정보 추출
    // $payment_wallet_info = $exchangeapi->get_row_wallet($payment_info->userno, $symbol);
    // if(!$payment_wallet_info->address) {
    //     $exchangeapi->error('050',__('There is no payment wallet.').' '.str_replace('{symbol}', $symbol, __('Please create a payment wallet for {symbol}.')));
    // }
    // // 트랜젝션 저장.
    // $r = $exchangeapi->add_wallet_txn ($payment_info->userno, $payment_wallet_info->address, $symbol, $sender_wallet->address, 'P', 'I', $fee, 0, 0, 'D', $txnid, date('Y-m-d H:i:s'),$payment_info->method);// DB 처리라 상태는 완료로 처리함.
    // if(!$r || !$txnid) {
    //     $exchangeapi->error('005',__('Failed to send.').' '.__('A system error has occurred.'));
    // }
    // // 잔액 반영
    // $exchangeapi->add_wallet($payment_info->userno, $symbol, $fee); // 받는 사람의 지갑번호가 있는경우만 받는사람 지갑에 잔액 추가. 없는 경우 외부 지갑이라 패스.
}

// DB 트랜젝션 끝
$exchangeapi->transaction_end('commit');

// 보내기 알림. 보낸사람. $user_info = $exchangeapi->get_member_info($userno);
$title = '[Morrow Wallet] 보내기 안내 ';
if($receiver_info) {
	$body = "{$user_info->name}님이 ".($receive_amount*1)." {$symbol}을 보내셨습니다." . date('m-d H:i');
	$user_token = $exchangeapi->query_list_one("SELECT fcm_tokenid FROM js_member_device WHERE userno='" . $exchangeapi->escape($receiver_info->userno) . "' GROUP BY fcm_tokenid ");
	$exchangeapi->send_fcm_message($user_token, $body, $title);
}
$receiver = $receiver_info->name ? $receiver_info->name : $receiver_address;
$body = "{$receiver}님에게 ".($send_amount*1)." {$symbol}을 보냈습니다." . date('m-d H:i');
$user_token = $exchangeapi->query_list_one("SELECT fcm_tokenid FROM js_member_device WHERE userno='" . $exchangeapi->escape($user_info->userno) . "' GROUP BY fcm_tokenid ");
$exchangeapi->send_fcm_message($user_token, $body, $title);


// response
$exchangeapi->success(array('txnid'=>$txnid));
