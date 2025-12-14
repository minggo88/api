<?php
include dirname(__file__) . "/../../lib/ExchangeApi.php";

// 실행시간 여유 (운영 504 완화용: nginx timeout이 더 짧으면 이건 효과 없음)
@set_time_limit(120);
@ini_set('max_execution_time', '120');

$t0 = microtime(true);

// if($_SERVER['REMOTE_ADDR']!='61.74.240.65') {$exchangeapi->error('001','시스템 정검중입니다.');}
$exchangeapi->set_logging(true);
$exchangeapi->set_log_dir($exchangeapi->log_dir.'/'.basename(__dir__).'/');
$exchangeapi->set_log_name('');
$exchangeapi->write_log("REQUEST: " . json_encode($_REQUEST));

$_receive_day = 1;

// -------------------------------------------------------------------- //

session_start();
session_regenerate_id(); // 로그인할때마다 token 값을 바꿉니다.

// validate parameters
$social_id = checkEmpty(loadParam('social_id')); // userid로 사용합니다.
$mobile_calling_code = setDefault(loadParam('mobile_calling_code'),'82'); // 국제전화번호
$mobile_country_code = setDefault(loadParam('mobile_country_code'),'KR'); // 국가코드
$social_name = checkSocialName(setDefault(loadParam('social_name'),''));
$mobile = checkMobileNumber(setDefault(loadParam('mobile'), ''));
$name = setDefault(loadParam('name'),'');
$nickname = setDefault(loadParam('nickname'),'');
$email = checkEmail(setDefault(loadParam('email'), ''));

$userpw = checkEmpty(loadParam('userpw'),'');
$pin = setDefault(loadParam('pin'),'');

if($social_name=='guest' || $social_name=='kakao') {
    $pin = '    '; // guest 비번은 통일.
} else {
    if(strlen($pin)!=6) {
        $exchangeapi->error('010',__('Please enter a 6-digit number.'));
    }
}

$bool_email = setDefault($_REQUEST['bool_email'], '0'); // 이메일 수신여부 1: 수신, 0:비수신
$bool_marketing = setDefault($_REQUEST['bool_marketing'], '0'); // 마케싱 정보 수신여부. 1:수신, 0:미수신

// PMS 추가 정보 (pms_createId에서만 넘어옴. 없으면 스킵)
$hospital = setDefault(loadParam('hospital'), '');
$address = setDefault(loadParam('address'), '');
$detailAddress = setDefault(loadParam('detailAddress'), '');

// --------------------------------------------------------------------------- //
// 데이터 가공

if($mobile) {
    $mobile = checkMobileNumber($mobile);
    $mobile = $exchangeapi->reset_phone_number($mobile);
}

if($social_name=='mobile' || $social_name=='kakao' || $social_name=='naver') {
    $social_id = checkMobileNumber($social_id);
    $social_id = $exchangeapi->reset_phone_number($social_id);
    $social_id = checkIncludedCallingCode($social_id);
}
if($social_name=='email') {
    $email = $social_id;
}

switch($social_name) {
    case 'email':
        $mobile_country_code = $exchangeapi->get_country_code($_SERVER['REMOTE_ADDR']);
        $mobile_country_code = $mobile_country_code ? $mobile_country_code : 'KR';
        break;

    case 'kakao':
    case 'naver':
        $mobile_country_code = 'KR';
        break;

    case 'google':
    case 'mobile':
        if(!$mobile_country_code) {
            $mobile_country_code = $exchangeapi->get_country_code($_SERVER['REMOTE_ADDR']);
        }
        if(!$mobile_country_code && $mobile) {
            $country_data = $exchangeapi->get_country();
            foreach($country_data as $row) {
                $country_calling_code = str_replace('+','',$row->colling_code);
                if(preg_match('/^('.$country_calling_code.'|\+'.$country_calling_code.')/', $mobile)) {
                    $mobile_country_code = $row->code; break;
                }
            }
        }
        break;
}

$exchangeapi->set_language_by_countrycode($mobile_country_code);

// 디비에서 사용하는 아이디, 비번으로 변경.
$userid = $social_name . $social_id;
$userpw = $userpw ? md5($userpw) : '';
$pin = $pin ? md5($pin) : '';

$exchangeapi->write_log("STEP t=" . round(microtime(true)-$t0,3) . " after-params");

// --------------------------------------------------------------------------- //

// 마스터 디비 사용하도록 설정.
$exchangeapi->set_db_link('master');

// 계정 정보 확인.
$member = $exchangeapi->get_member_info_by_userid($userid);
if($member) {
    $exchangeapi->error('041', __('Already joined.').' '.__('Please login!'));
}

$cnt_withdraw = $exchangeapi->query_one("select count(*) from js_withdraw where userid='{$exchangeapi->escape($userid)}' ");
if($cnt_withdraw > 0) {
    $exchangeapi->error('105', __('Please enter another ID.'));
}

/**
 * 운영 504/락 완화:
 * - 트랜잭션은 DB insert/update(+PMS 저장)까지만 묶고
 * - 지갑 생성(외부/무거운 작업 가능)은 트랜잭션 밖으로 뺌
 */
$exchangeapi->transaction_start();

try {

    // 가입
    $sql = "insert into js_member set
        userid='{$exchangeapi->escape($userid)}',
        userpw='{$exchangeapi->escape($userpw)}',
        `name`='{$exchangeapi->escape($name)}',
        nickname='{$exchangeapi->escape($nickname)}',
        phone='',
        email='{$exchangeapi->escape($email)}',
        mobile='{$exchangeapi->escape($mobile)}',
        zipcode='',
        address_a='',
        address_b='',
        level_code='',
        regdate=UNIX_TIMESTAMP(),
        bool_sms=0,
        bool_email='{$exchangeapi->escape($bool_email)}',
        bool_marketing='{$exchangeapi->escape($bool_marketing)}',
        pin='{$exchangeapi->escape($pin)}',
        mobile_country_code='{$exchangeapi->escape($mobile_country_code)}'
    ";
    $exchangeapi->query($sql);

    $member = $exchangeapi->get_member_info_by_userid($userid);
    $new_userno = $member->userno;

    // PMS 저장 (pms_createId에서만)
    if($hospital !== '') {

        // pms_createId라면 주소도 필수로 강제
        if($address=='') { $exchangeapi->error('202', '주소를 입력해주세요.'); }
        if($detailAddress=='') { $exchangeapi->error('203', '상세주소를 입력해주세요.'); }

        $sql = "
            insert into js_member_pms
                (userno, hospital_name, address, address_detail, regdate)
            values
                (
                    '{$exchangeapi->escape($new_userno)}',
                    '{$exchangeapi->escape($hospital)}',
                    '{$exchangeapi->escape($address)}',
                    '{$exchangeapi->escape($detailAddress)}',
                    UNIX_TIMESTAMP()
                )
            on duplicate key update
                hospital_name=values(hospital_name),
                address=values(address),
                address_detail=values(address_detail)
        ";
        $exchangeapi->query($sql);
    }

    // 가입 방법 저장
    $join_mehod_columns = $exchangeapi->query_one("SHOW COLUMNS FROM `js_member` LIKE 'join_method'");
    if(!$join_mehod_columns) {
        $exchangeapi->query("ALTER TABLE `js_member`
            ADD COLUMN `join_method` VARCHAR(50) DEFAULT '' NOT NULL
            COMMENT '가입경로(가입시 social_name으로 사용). guest, mobile, email, naver, kakao, google, facebook, ...'
            AFTER `userpw`
        ");
    }
    $exchangeapi->query("UPDATE `js_member`
        SET `join_method`='".$exchangeapi->escape($social_name)."'
        WHERE userno='{$exchangeapi->escape($member->userno)}' AND `join_method`=''
    ");

    // 가입자 아이피 저장
    $join_ip = $exchangeapi->query_one("SHOW COLUMNS FROM `js_member` WHERE `Field`='join_ip'");
    if(!$join_ip) {
        $exchangeapi->query("ALTER TABLE `js_member` ADD COLUMN `join_ip` VARCHAR(100) DEFAULT '' NOT NULL COMMENT '가입IP' ");
    }
    $exchangeapi->query("UPDATE `js_member`
        SET `join_ip`='".$exchangeapi->escape($_SERVER['REMOTE_ADDR'])."'
        WHERE userno='{$exchangeapi->escape($member->userno)}' AND `join_ip`=''
    ");

    $exchangeapi->transaction_end('commit');

} catch(Exception $e) {
    $exchangeapi->transaction_end('rollback');
    $exchangeapi->write_log("ERROR: " . $e->getMessage());
    $exchangeapi->error('999', '가입 처리 중 오류가 발생했습니다.');
}

$exchangeapi->write_log("STEP t=" . round(microtime(true)-$t0,3) . " after-db-commit");

// --------------------------------------------------------------------------- //
// 지갑 생성(무거울 수 있는 구간): 트랜잭션 밖

// 기본 코인(ETH 생성)
if(__API_RUNMODE__=='live') {
    if(!$exchangeapi->query_one("select address from js_exchange_wallet where symbol='ETH' and userno='{$member->userno}' ")) {
        $exchangeapi->write_log('ETH wallet create start');
        $address_eth = $exchangeapi->create_wallet($member->userno, 'ETH');
        $exchangeapi->write_log('ETH wallet create end. address: '. $address_eth);
        $exchangeapi->save_wallet($member->userno, 'ETH', $address_eth);
    }
}

// 기본 지갑 생성
$default_coins = array('KRW','USD');
foreach($default_coins as $coin) {
    $exchangeapi->write_log("wallet create start: ".$coin);
    $address = $exchangeapi->create_wallet($new_userno, $coin);
    $exchangeapi->save_wallet($new_userno, $coin, $address);
    $exchangeapi->write_log("wallet create end: ".$coin);
}

$exchangeapi->write_log("STEP t=" . round(microtime(true)-$t0,3) . " after-wallets");

// login - userno, $userid, $name, $level_code)
$exchangeapi->login($member->userno, $member->userid, $member->name, $member->level_code);

// 나는 몇번째 지갑인가?
$my_wallet_no = $exchangeapi->query_one("SELECT COUNT(*)+1 FROM js_member WHERE 1000<=userno AND userno<'{$member->userno}' ");

// response
$exchangeapi->success(array('token'=>session_id(),'my_wallet_no'=>$my_wallet_no,'userno'=>$new_userno));