<?php
include dirname(__file__) . "/../../lib/TradeApi.php";
// if(date('YmdH')>='2021030309' && date('YmdH')<'2021030314') {
//     if($_SERVER['REMOTE_ADDR']!='61.74.240.65') {$tradeapi->error('001',__('시스템 정검중입니다.'));}
// }

$tradeapi->set_logging(true);
// if(__API_RUNMODE__=='live'||__API_RUNMODE__=='loc') {
	// $tradeapi->set_log_dir(__dir__.'/../../log/'.basename(__dir__).'/');
	$tradeapi->set_log_dir($tradeapi->log_dir.'/'.basename(__dir__).'/');
// } else {
// 	$tradeapi->set_log_dir(__dir__.'/');
// }
$tradeapi->set_log_name('');
$tradeapi->write_log("REQUEST: " . json_encode($_REQUEST));

// monson은 DB로만 작동
// 30초 작동 시간
// 실제 작업 시간이 30초가 넘으면 rollback 처리해서 화면에 애러 표시.
// 롤백시 오류 발생하므로 background daemon 처리로 변경.
// 프론트단 timeout 기능은 제거.

// 로그인 세션 확인.
$tradeapi->checkLogin();
$userno = $tradeapi->get_login_userno();
$userid = $tradeapi->get_login_userid();
$user_info = $tradeapi->get_member_info_by_userid($userid);

// validate parameters
// $run_mode = setDefault(loadParam('run_mode'), '1'); // 실행모드. 일반 지갑: NORMAL, 카드(인터바일):CARD 카드결제 연동시 사용하기.
$symbol = checkSymbol(strtoupper(setDefault(loadParam('symbol'), ''))); // 코인
// $sender_walletname = checkEmpty(loadParam('sender_walletname'), 'sender walletname'); // 송신인 주소
// $receiver_walletname = checkEmpty(loadParam('receiver_walletname'), 'receiver walletname'); // 수취인 주소
$receiver_address = checkEmpty(loadParam('receiver_address'), 'receiver address'); // 수취인 주소
// 받는사람 지갑 주소 validation. - 이전 send 화면에서 address 채크하고 전송해야 함. 채크 결과 성공이면 Y 값을 넘겨야 하고 아니면 전부 fail 처리함.
// $receiver_address_checked = checkAddressChecked(checkEmpty(loadParam('receiver_address_checked'], 'receiver_address_checked')); // 수취인 주소 확인 여부
$amount = checkZero(checkNumber(loadParam('amount')), 'amount');// 송금량
$pin = checkEmpty(loadParam('pin')); // 계좌 송금 비번.
// $otppw = checkEmpty(loadParam('otppw')); // 계좌 송금 비번.
$nft_id = setDefault(loadParam('nft_id'), ''); // NFT 토큰 아이디

// $fee = checkNumber(setDefault(loadParam('fee'), '0'));// 수수료 카드결제 연동시 사용하기.
$msg = setDefault(loadParam('msg'), '');// 보내는 메시지

// --------------------------------------------------------------------------- //

// payment infor 확인. 카드결제 연동시 사용하기.
// $payment_info = $tradeapi->get_payment_info($run_mode);
// if(!$payment_info->payment_no){
//     $tradeapi->error('000',__('Please enter the correct run mode.'));
// }

// pin 번호 확인.
if($user_info->pin!=md5($pin)) {
    $tradeapi->error('025',__('Please enter the correct PIN number.'));
}


// otp
// if($user_info->otpkey) {
// 	// include dirname(__file__) . "/../api/lib/GoogleAuthenticator.php";
// 	include dirname(__file__) . "/../../lib/GoogleAuthenticator.php";
// 	$ga = new PHPGangsta_GoogleAuthenticator();
// 	$c = $ga->getCode($user_info->otpkey);
// 	$otp_check = $c===$otppw;
// 	if(!$otp_check) {
// 		$tradeapi->error('026',__('OTP Key를 다시입력해주세요.'));
// 	}
// } else {
// 	$tradeapi->error('027',__('OTP를 등록해주세요.'));
// }

// 자동 잠금
// 1분에 10번 이상의 Send 요청이 발생한 경우. 자동 전송 차단.
if( $tradeapi->check_wallet_autolock($userno, $symbol) ) {
	$tradeapi->error('057',__('Too many send occurred in a short time, and the send function was automatically locked. To unlock, contact administrator.'));
}


// 로그인 사용자 지갑정보 가져오기.
$sender_wallet = $tradeapi->get_wallet($userno, $symbol);
$sender_wallet = $sender_wallet[0];
if(!$sender_wallet->address) {
	$tradeapi->error('048',__('You do not have a wallet.').' '.__('Please create a wallet.'));
}
// check locked
if($sender_wallet->locked != 'N') {
	$tradeapi->error('048',__('Your wallet is locked and cannot be send.'));
}

// send 가능금액 보정
$sendable_amount = $tradeapi->get_sendable_amount($userno, $symbol);
if($sendable_amount < $amount ) {
	$tradeapi->error('050',__('There is not enough balance to send.'));
}


// 화폐정보
$currency = $tradeapi->query_fetch_object("select * from js_exchange_currency where symbol='{$tradeapi->escape($symbol)}' ");

// 받는 사람 지갑 주소 확인 없으면 사용자가 작성한 값을 사용.
// 지갑이름이 아니라 지갑주소로 들어 오는 경우인지 확인. 즉, $receiver_walletname으로 지갑 정보 조회
$receiver_wallet = $tradeapi->get_wallet_by_address($receiver_address, $symbol);
if(!$receiver_wallet || !$receiver_wallet->userno) {
	// 받는사람을 그냥 전화번호로 전달 받은경우... 확인
	$receiver_userno = $tradeapi->query_one("select userno from js_member where mobile='{$tradeapi->escape($receiver_address)}' ");
	$receiver_wallet = $tradeapi->query_fetch_object("select * from js_exchange_wallet where userno='{$tradeapi->escape($receiver_userno)}' and symbol='{$tradeapi->escape($symbol)}'");
	// 받는사람의 회원번호가 있는데... 지갑이 없으면 지갑을 만들어준다.
	if($receiver_userno && !$receiver_wallet) {
		$address = $tradeapi->create_wallet($receiver_userno, $symbol);
		if(! $address) {
			$coind = $tradeapi->load_coind($symbol);
			$errmsg = $coind->getError();
			$tradeapi->error('014',__('수신자의 지갑이 없어 생성하려 했으나 생성하지 못했습니다.').$errmsg);
		}
		$tradeapi->save_wallet($receiver_userno, $symbol, $address);
		$receiver_wallet = $tradeapi->query_fetch_object("select * from js_exchange_wallet where userno='{$tradeapi->escape($receiver_userno)}' and symbol='{$tradeapi->escape($symbol)}'"); // 받는사람 지갑을 생성후 변수에 정보 안담아서 전달안되는 버그 수정.
	}

	// 암호화폐는 외부 지갑으로 보낼 수 있어서 여기 없을 수 있음.
	// 암호화폐가 아닌것은 PAY(내부 지갑전용)라서 외부로 보낼수 없음.
	if($currency->crypto_currency=='N' && (!$receiver_wallet || !$receiver_wallet->userno)) {
		$tradeapi->error('050',__('There is no receiver wallet.').' '.__('Please enter a valid receiver address.'));
	}
}
$receiver_info = $tradeapi->get_member_info($receiver_wallet->userno);
// var_dump($userno, $symbol, $sender_wallet, $receiver_wallet); exit;

// 본인에게 보내는 건지 주소 확인
if($receiver_wallet->userno == $userno) {
	$tradeapi->error('030', __('다른 회원님에게 보내주세요.'));
}

// 외부 거래는 최소 전송 금액 제약을 둔다. 내부거래는 제한없음.
if( !$receiver_wallet && !$receiver_wallet->userno && $currency->out_min_volume >= 1 && $currency->out_min_volume > $amount) {
	if($_SERVER['REMOTE_ADDR']!='61.74.240.65') { // 회사에서 테스트할때 사용
		$tradeapi->error('031', str_replace('{out_min_volume} {symbol}',($currency->out_min_volume*1).' '.$currency->symbol, __('외부로 송금시에는 최소 {out_min_volume} {symbol}이상으로 전송해주세요')));
	}
}
if( !$receiver_wallet && !$receiver_wallet->userno && $currency->out_max_volume >= 1 && $currency->out_max_volume < $amount) {
	$tradeapi->error('032', str_replace('{out_min_volume} {symbol}',($currency->out_min_volume*1).' '.$currency->symbol, __('외부로 송금시에는 최대 {out_min_volume} {symbol}미만으로 전송해주세요')));
}
// $tradeapi->error('030', 'test');exit;

// 더블클릭 막기 - 실수로 동일한 요청(발송계좌, 금액 동일)이 5초이내로 온것이 있는지 확인. 있으면 해당건은 작업 중단.
$duplicated = $tradeapi->check_duplicated_transaction($sender_wallet->userno, $amount);
if($duplicated) {
	$tradeapi->error('063', __('We blocked the same amount transfer in a short period of time to prevent duplicate shipments. Please send it after a while to send the same amount.'));
}

// nft 정보 확인
if($nft_id) {
	$nft_info = $tradeapi->query_fetch_object("SELECT * FROM js_exchange_wallet_nft WHERE symbol='{$tradeapi->escape($symbol)}' AND tokenid='{$tradeapi->escape($nft_id)}' AND userno='{$tradeapi->escape($userno)}' ");
	if(!$nft_info) {
		$tradeapi->error('160',__('NFT 정보를 찾지 못했습니다.'));
		// {"token":"f130525b6d9a713a210e8378cd4a2cd5d6e99f4d4fba0e5b586b10906434113e","symbol":"NFTN","receiver_address":"0x1099e1de116041bf03e1d1a9893ecd996c743076","pin":"123400","amount":"1","nft_id":"669"}
	}
	if($nft_info->userno != $userno) {
		$tradeapi->error('161',__('NFT의 소유주만 전송할 수 있습니다.'));
	}
	// 보유 수량확인
	if($nft_info->amount < $amount) {
		$tradeapi->error('163',__('보유수량보다 많이 전송할 수 없습니다.'));
	}
	$auction_goods_info = $tradeapi->query_fetch_object("SELECT * FROM js_auction_goods WHERE nft_symbol='{$tradeapi->escape($symbol)}' AND nft_id='{$tradeapi->escape($nft_id)}' ");
	// 분할된 NFT는 외부로 발송할 수 없음.
	if($auction_goods_info->nft_max_supply > 1) {
		$tradeapi->error('162',__('분할된 NFT는 외부로 전송할 수 없습니다.'));
	}
}

// 출금 신청.
// $txnid = $tradeapi->send_coin ($symbol, $sender_wallet->address, $sender_wallet->account, $receiver_address, $amount, $fee, $msg, $sender_wallet->walletkey);
$txnid = ''; // 백그라운드로 처리.

// 수수료 조회
// UKRW 는 수수료가 미발생하기때문에 0으로 처리하지만, BTC 같은경우 수수료가 발생해서 send_coin을 실행후 수수료 계산을 해야 합니다.
// 내부 외부 구분없이 수수료 적용.
$fee = $tradeapi->cal_fee($symbol, 'withdraw', $amount); //-> 외부 전송시
// $fee = $tradeapi->cal_fee($symbol, 'internal', $amount); -> 내부 전송시 - 미정의항목
// 내부 전송시 수수료와 외부 전송시 수수료를 구분해서 처리해야 함. 구조상 외부 전송은 없기때문에 내부 수수료만 생각함. 일단 0으로 처리함.
// $fee = 0;
// 외부거래이고 화폐에 출금 수수료가 있으면 수수료 반영함.
// if(!$receiver_wallet) {
// 	if($currency->fee_out) {
// 		$fee = $currency->fee_out*1; //
// 	}
// }

// 세금
// $tax = $tradeapi->cal_tax($symbol, 'withdraw', $amount); -> 외부 전송시
// $tax = $tradeapi->cal_tax($symbol, 'internal', $amount); -> 내부 전송시 - 미정의항목
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
	$tradeapi->error('050',__('There is not enough balance to send.'));
}


// 상태값 : 외부 송금은 O(준비중), 내부송금은 D(종료)
$status = 'D';
if(!$receiver_wallet) {
	$status = 'O';
}
// var_dump($fee, $tax, $amount, $receive_amount, $sender_wallet, $receiver_wallet); exit;

// DB 트랜젝션 시작
$tradeapi->transaction_start();


// 발송자 처리
// 트랜젝션에 저장.
$r = $tradeapi->add_wallet_txn ($userno, $sender_wallet->address, $symbol, $receiver_address, 'S', 'O', $amount, $fee, $tax, $status, '', date('Y-m-d H:i:s'), $msg, '', 'COIN', $nft_id); // DB 처리라 상태는 완료로 처리함. ,$payment_info->method는 카드결제 연동시 사용하기.
$txnid = $tradeapi->_recently_query['last_insert_id'];
if(!$r || !$txnid) {
	$tradeapi->error('005',__('Failed to send.').' '.__('A system error has occurred.'));
}
// 잔액 반영
$tradeapi->del_wallet($userno, $symbol, $send_amount);
// nft 보유량 수정 (분할 NFT는 외부 전송이 불가하기 때문에 보유량 전부를 삭제합니다.)
if($nft_id) {
	$tradeapi->query_fetch_object("DELETE FROM js_exchange_wallet_nft WHERE symbol='{$tradeapi->escape($symbol)}' AND tokenid='{$tradeapi->escape($nft_id)}' AND userno='{$tradeapi->escape($userno)}' ");
}

// 수신자 처리.
if($receiver_wallet) {
	// 트랜젝션 저장.
	$r = $tradeapi->add_wallet_txn ($receiver_wallet->userno, $receiver_wallet->address, $symbol, $sender_wallet->address, 'S', 'I', $receive_amount, $fee, $tax, $status, $txnid, date('Y-m-d H:i:s'), $msg, '', 'COIN', $nft_id);// DB 처리라 상태는 완료로 처리함. ,$payment_info->method는 카드결제 연동시 사용하기.
	if(!$r || !$txnid) {
		$tradeapi->error('005',__('Failed to send.').' '.__('A system error has occurred.'));
	}
	// 잔액 반영
	$tradeapi->add_wallet($receiver_wallet->userno, $symbol, $receive_amount); // 받는 사람의 지갑번호가 있는경우만 받는사람 지갑에 잔액 추가. 없는 경우 외부 지갑이라 패스.
	// nft 수량 수정
	if($nft_id) {
		$tradeapi->query_fetch_object("INSERT INTO js_exchange_wallet_nft SET symbol='{$tradeapi->escape($symbol)}', tokenid='{$tradeapi->escape($nft_id)}', userno='{$tradeapi->escape($userno)}', amount='{$tradeapi->escape($amount)}', reg_date=NOW(), mode_date=NULL ");
	}
// } else {
// 	$tradeapi->error('006',__('Failed to send.')); // 외부 출금 차단 ... OTP 추가시 해제
}


// 수수료 지급 - 결제사마다 수수료는 다름. 전달 받은 수수료를 그대로 차감해 전달함.
if($fee>0) {
    // 결제 수수료 지갑 정보 추출 - 전송수수료를 관리 지갑에 전송합니다.(수수료 내역 확인용 DB 처리입니다.)
    $fee_wallet_info = $tradeapi->get_row_wallet($currency->fee_save_userno, $symbol);
    if(!$fee_wallet_info->address) {
        $tradeapi->error('050',__('There is no fee wallet.').' '.str_replace('{symbol}', $symbol, __('Please create a fee wallet for {symbol}.')));
    } else {
		// 트랜젝션 저장. txn_type F:수수료
		$tradeapi->add_wallet_txn ($fee_wallet_info->userno, $fee_wallet_info->address, $symbol, $sender_wallet->address, 'F', 'I', $fee, 0, 0, 'D', $txnid, date('Y-m-d H:i:s'),'');// DB 처리라 상태는 완료로 처리함.
		// 잔액 반영
		$tradeapi->add_wallet($fee_wallet_info->userno, $symbol, $fee); // 받는 사람의 지갑번호가 있는경우만 받는사람 지갑에 잔액 추가. 없는 경우 외부 지갑이라 패스.
	}

	// old
    // // 결제 수수료 지갑 정보 추출
    // $payment_wallet_info = $tradeapi->get_row_wallet($payment_info->userno, $symbol);
    // if(!$payment_wallet_info->address) {
    //     $tradeapi->error('050',__('There is no payment wallet.').' '.str_replace('{symbol}', $symbol, __('Please create a payment wallet for {symbol}.')));
    // }
    // // 트랜젝션 저장.
    // $r = $tradeapi->add_wallet_txn ($payment_info->userno, $payment_wallet_info->address, $symbol, $sender_wallet->address, 'P', 'I', $fee, 0, 0, 'D', $txnid, date('Y-m-d H:i:s'),$payment_info->method);// DB 처리라 상태는 완료로 처리함.
    // if(!$r || !$txnid) {
    //     $tradeapi->error('005',__('Failed to send.').' '.__('A system error has occurred.'));
    // }
    // // 잔액 반영
    // $tradeapi->add_wallet($payment_info->userno, $symbol, $fee); // 받는 사람의 지갑번호가 있는경우만 받는사람 지갑에 잔액 추가. 없는 경우 외부 지갑이라 패스.
}

// DB 트랜젝션 끝
$tradeapi->transaction_end('commit');

// 보내기 알림. 보낸사람. $user_info = $tradeapi->get_member_info($userno);
$title = '[NFTN Wallet] 보내기 안내 ';
if($receiver_info) {
	$body = "{$user_info->name}님이 ".($receive_amount*1)." {$symbol}을 보내셨습니다." . date('m-d H:i');
	$user_token = $tradeapi->query_list_one("SELECT fcm_tokenid FROM js_member_device WHERE userno='" . $tradeapi->escape($receiver_info->userno) . "' GROUP BY fcm_tokenid ");
	$tradeapi->send_fcm_message($user_token, $body, $title);
}
$receiver = $receiver_info->name ? $receiver_info->name : $receiver_address;
$body = "{$receiver}님에게 ".($send_amount*1)." {$symbol}을 보냈습니다." . date('m-d H:i');
$user_token = $tradeapi->query_list_one("SELECT fcm_tokenid FROM js_member_device WHERE userno='" . $tradeapi->escape($user_info->userno) . "' GROUP BY fcm_tokenid ");
$tradeapi->send_fcm_message($user_token, $body, $title);


// response
$tradeapi->success(array('txnid'=>$txnid));
