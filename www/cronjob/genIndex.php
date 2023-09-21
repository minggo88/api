<?php

/**
 * 거래소 지수 데이터 생성.
 * 
 * 실행 명령어: 
 * $ php genIndex.php start
 * 종료 명령어:
 * $ php genChart.php stop
 */
include(dirname(__file__) . '/../lib/TradeApi.php');

ignore_user_abort(1);
set_time_limit(0);

$tradeapi->logging = false;
$tradeapi->set_log_dir(dirname(__file__) . '/../log/' . basename(__file__, '.php') . '/');
$tradeapi->set_log_name('');
$tradeapi->write_log('genIndex.php start.');

$filename = __file__;

// 프로세스 작동중인지 확인. 작동중이면 종료.
@exec("ps  -ef| grep -i '{$filename}' | grep -v grep", $output);
if (count($output) > 1) {
    $tradeapi->write_log('프로세스 중복으로 종료.');
    exit();
}

$tradeapi->set_db_link('master');

$now = date('Y-m-d');

$sql = array();

// 판매 종목 정보 조회
$items = $tradeapi->query_list_object("SELECT symbol, exchange FROM js_trade_currency WHERE active='Y' AND symbol<>'{$tradeapi->escape($tradeapi->default_exchange)}'");
$cnt = count($items);
for ($i = 0; $i < $cnt; $i++) {

    $item = $items[$i];
    $symbol = $item->symbol;
    $exchange = $item->exchange;
    $table_chart = 'js_trade_' . strtolower($symbol) . strtolower($exchange) . '_chart';

    if ($symbol && $exchange && $tradeapi->check_table_exists($table_chart)) {

        // 등급별로 쿼리를 만듧니다.
        $grades = array('S', 'A', 'B');
        foreach ($grades as $g) {

            // SELECT symbol, `date`, CLOSE, goods_grade, IFNULL(cnt*`close`, 0) amount FROM (
            //     SELECT 'GF0AP66RBP' symbol, tc.date, tc.close, tc.goods_grade, (SELECT SUM(confirmed) FROM js_exchange_wallet ew WHERE ew.symbol='GF0AP66RBP' AND ew.`goods_grade`=tc.`goods_grade` AND ew.userno>0 ) cnt
            //     FROM js_trade_gf0ap66rbpkrw_chart tc
            //     WHERE tc.term='1d' AND tc.date <= '2022-12-26 23:59:59' AND tc.goods_grade='S' 
            //     ORDER BY tc.date DESC LIMIT 1
            // ) t 
            $check_grade_sql = "SELECT goods_grade,price_close FROM js_trade_price WHERE symbol = 'gohqyq8an2' AND price_high > 0";
            $data = $tradeapi->query_list_object($check_grade_sql);
            
		    if($data[0] === $g){
                $sql[] = "SELECT symbol, `date`, goods_grade, `close`, cnt, IFNULL(cnt*`close`, 0) amount FROM ( SELECT '{$symbol}' symbol, tc.date, tc.close, tc.goods_grade, (SELECT sum(confirmed) FROM js_exchange_wallet ew WHERE ew.symbol='{$symbol}' AND ew.`goods_grade`=tc.`goods_grade` AND ew.userno>0 ) cnt FROM {$table_chart} tc  WHERE tc.term='1d' AND tc.date <= '{$now} 23:59:59' AND tc.goods_grade='{$g}' ORDER BY tc.date DESC LIMIT 1 ) t ";
                
                $check_grade_sql2 = "SELECT count(*) FROM ( SELECT '{$symbol}' symbol, tc.date, tc.close, tc.goods_grade, (SELECT sum(confirmed) FROM js_exchange_wallet ew WHERE ew.symbol='{$symbol}' AND ew.`goods_grade`=tc.`goods_grade` AND ew.userno>0 ) cnt FROM {$table_chart} tc  WHERE tc.term='1d' AND tc.date >= '{$now} 00:00:00' AND tc.goods_grade='{$g}' ORDER BY tc.date DESC LIMIT 1 ) t ";
                $cnt2 = $tradeapi->query_one($check_grade_sql);
                if ($cnt2 < 1) {
                    $sql = "INSERT INTO `kkikda`.`{$table_chart}` (`term`, `date`, `goods_grade`, `open`, `high`, `low`, `close`) VALUES ('1d', '{$now} 00:00:00', '{$g}', '{$data[1]}', '{$data[1]}', '{$data[1]}', '{$data[1]}');";
                    $tradeapi->query($sql);
                }
            }
        }

    }
}

if($sql) {
    // 전체거래종목 평가금액
    $eval_amount = $tradeapi->query_one("select SUM(amount) from ( ".implode(' UNION ALL ', $sql)." ) t ");
    $tradeapi->query("INSERT INTO js_trade_index set `date`='{$now}', code='eval_amount', `value`='{$eval_amount}' ON DUPLICATE KEY UPDATE `value`='{$eval_amount}' ");
    
    // KKIKDA 지수
    $KKIDA = real_number($eval_amount/30000000, 2, 'round');
    $tradeapi->query("INSERT INTO js_trade_index set `date`='{$now}', code='kkikda', `value`='{$KKIDA}' ON DUPLICATE KEY UPDATE `value`='{$KKIDA}' ");

    $nowTime = time();
    $ctime = date('i', $nowTime);
    
    //$test = "INSERT INTO js_test set `text1` = '{$nowTime}', `tvalue` = '{$ctime}' ";
    //$tradeapi->query($test);


    //////////////////////////////////////////////////

    // 요청 바디 설정
    $publicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAsZIbPm4TtJ3kz4fm0v2SdJHN5ej5sCxL1PVLcVo55p+K8ivhvUnzaM0a0vfcxVaBN5q4aQMKXkWVZ0YqFQxFGPl9lJ/ndbY4mBLWIvBsA7U9NN4UFwSbuEdL7TYN9gPhKyyA/5ntSB9E0k6lH43aa1eyRaY+Q6SG+OwJxueib/A3uO+KDKOTClW9rzXbA1/5gwe0R1rBRj6FBMWo+qXfF/+8LPveOu9PMn9W5xboQ4/DvIUyTTroIfl26x/Kb/o5TXgbidSSTUhPzwNTSAvO6gxhVM+jD1Sq8qECJtMrE+DzT4faqv+O2IyfB42dlJ22BcaHZdsRGsVt57xsrO0wKwIDAQAB';
    $password1 = 'Dpszlfndi1!';
    $pw = $tradeapi->encryptRSA($password1, $publicKey);

    $key = $tradeapi->search_kkikdageo();

    $tagetData = file_get_contents(dirname(__FILE__).'/../np/sk.bin');

    // 복호화
    $decryptedData = openssl_decrypt($tagetData, 'AES-256-CBC', $key, 0, '1234567890123456');
    $acpw = $tradeapi->encryptRSA($decryptedData, $publicKey);

    $test = "INSERT INTO js_test set `text1` = '{$key}', `tvalue` = '{$tagetData}' ;";
    $tradeapi->query($test);
	
	//헥토 api 호출
	if ($ctime % 10 === 0) {
		$sql = "SELECT COUNT(*) FROM js_exchange_wallet_txn WHERE symbol = 'KRW' AND status = 'O';";

		$cnt = $tradeapi->query_one($sql);

		if($cnt >0){
            $sql = "SELECT txnid,userno,address_relative,amount FROM js_exchange_wallet_txn WHERE symbol = 'KRW' AND status = 'O';";
            $currencies = $tradeapi->query_list_object($sql);

            //api처리내역
            /***
             *    -------------api 연결 여기부터
             */

            $clientId = 'ef9294ee-dad6-4796-9077-cd6f41773059';
            $clientSecret = 'b078b370-6465-427f-b389-427c9ac72730';
            $publicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAsZIbPm4TtJ3kz4fm0v2SdJHN5ej5sCxL1PVLcVo55p+K8ivhvUnzaM0a0vfcxVaBN5q4aQMKXkWVZ0YqFQxFGPl9lJ/ndbY4mBLWIvBsA7U9NN4UFwSbuEdL7TYN9gPhKyyA/5ntSB9E0k6lH43aa1eyRaY+Q6SG+OwJxueib/A3uO+KDKOTClW9rzXbA1/5gwe0R1rBRj6FBMWo+qXfF/+8LPveOu9PMn9W5xboQ4/DvIUyTTroIfl26x/Kb/o5TXgbidSSTUhPzwNTSAvO6gxhVM+jD1Sq8qECJtMrE+DzT4faqv+O2IyfB42dlJ22BcaHZdsRGsVt57xsrO0wKwIDAQAB';
            
            //토큰수령

            $url = "https://oauth.codef.io/oauth/token";
            $params = "grant_type=client_credentials&scope=read";
            $token2 = "";//accessToken

            $con = curl_init($url);
            curl_setopt($con, CURLOPT_POST, true);
            curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($con, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded"));

            $auth = $clientId . ":" . $clientSecret;
            $authEncBytes = base64_encode($auth);
            $authHeader = "Basic " . $authEncBytes;

            curl_setopt($con, CURLOPT_HTTPHEADER, array("Authorization: " . $authHeader));
            curl_setopt($con, CURLOPT_POSTFIELDS, $params);

            $response = curl_exec($con);
            $responseCode = curl_getinfo($con, CURLINFO_HTTP_CODE);
            curl_close($con);

            $headers = array();

            if ($responseCode == 200) {
                $tokenMap = json_decode(urldecode($response), true);
                //$tradeapi->error('049', __('토큰확인'. var_dump($tokenMap)));
                $token = implode(" ", $tokenMap);
                //$token = "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJzZXJ2aWNlX3R5cGUiOiIxIiwic2NvcGUiOlsicmVhZCJdLCJzZXJ2aWNlX25vIjoiMDAwMDAyNDk3MDAyIiwiZXhwIjoxNjg4NTM1NDE1LCJhdXRob3JpdGllcyI6WyJJTlNVUkFOQ0UiLCJQVUJMSUMiLCJCQU5LIiwiRVRDIiwiU1RPQ0siLCJDQVJEIl0sImp0aSI6IjkyNTJmMDRiLTgyN2YtNDAxMS05ODgxLTQxZGRhYzcxMTMxYyIsImNsaWVudF9pZCI6ImY1NTk1MjY0LTJkOTEtNDI3My05NDhjLTBmNGI2OTUxYmViMiJ9.ETcfmQ-oUiw7zayFlk1roXQ8j-gUVoZPfpJ7IoZ91MdK6te3sb-K8d2GqJ6qk8XEO-ee-8vblIUPyxFzewJOvsBLc7VBIl8keArjFfnus5l2VBmvDpwVFkJflMteaF8IKww9U7hRqqFWlt8Lz1MhQaZN1QpeeaDCH-3wGgpA432Bsw99X3e3gIt-dsxU98eZ1E2F_R9s5xSHCs5G2wXTqnMaMRzuuGCVEgrxupeXHBPw008EHauJa29LceSxAeFkYfCC1qOlYQTEAHbva1ireeW02zhcTe9sFE9Dr4AUrtouOa5gCOZ5WRgID27QBpH1Jkpww-xz0p-IJ5s-7h98jA";
                $accesstoken = explode(' ', $token);
                $token2 = $accesstoken[0];
                //$tradeapi->error('049', __('토큰확인'. $token.' / '.$token2));

                // 요청 헤더 설정
                $headers = array(
                'Content-Type: application/json; charset=UTF-8',
                'Authorization: Bearer '.$token2
                );
                //$tradeapi->error('049', __('헤더확인'. implode(" ", $headers)));
            } else {
                $tradeapi->error('049', __('토큰실패'. $responseCode));
            }

            // API 엔드포인트
            $apiUrl = 'https://api.codef.io';

            // 요청 바디 설정
            $password1 = 'Dpszlfndi1!';
            $pw = $tradeapi->encryptRSA($password1, $publicKey);
            $account = '23891002273004';

            /***
             * password 복호화
             */
            $key = $tradeapi->search_kkikdageo();

            $tagetData = file_get_contents(dirname(__FILE__).'/../np/sk.bin');

            // 복호화
            $decryptedData = openssl_decrypt($tagetData, 'AES-256-CBC', $key, 0, '1234567890123456');
            $acpw = $tradeapi->encryptRSA($decryptedData, $publicKey);

            //날자 만들기
            $year = date('Y'); // 현재 연도
            $month = date('m'); // 현재 월
            $firstDayOfMonth = $year . $month . '01';
            $today = date('Ymd');

            //1일일경우 전달과 비교
            if ($firstDayOfMonth === $today) {
                if ($month === '01') {
                // 1월 1일인 경우 전년도 12월 25일로 $firstDayOfMonth을 변경합니다
                $year = $year - 1;
                $firstDayOfMonth = $year . '1225';
                } else {
                // 그 외의 경우 전달의 25일로 $firstDayOfMonth을 변경합니다
                $previousMonth = sprintf('%02d', $month - 1);
                $firstDayOfMonth = $year . $previousMonth . '25';
                }
            }

            $body = array(
                "organization" => "0081",
                "fastId" => $account,
                "fastPassword" => $acpw,
                "id" => "KKIKDA2021",
                "password" => $pw,
                "account" => $account,
                "accountPassword" => $acpw,
                "startDate" => $firstDayOfMonth,
                "endDate" => $today,
                "orderBy" => "0",
                "identity" => "6238602033"
            );

            // 요청 생성
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, $apiUrl.'/v1/kr/bank/b/fast-account/transaction-list');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            // 요청 실행
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            // 응답 확인
            if ($httpCode == 200) {
                $decodedData = urldecode($response);
                $data = json_decode($decodedData, true);
                $apiArray = [];
                // result 데이터
                $apiArray = $data['data']['resTrHistoryList'];

                for ($i = count($apiArray) - 1; $i >= 0; $i--) {
                    $resAccountTrDate = $apiArray[$i]['resAccountTrDate'];
                    $resAccountTrTime = $apiArray[$i]['resAccountTrTime'];
                    $resAccountOut = $apiArray[$i]['resAccountOut'];
                    $resAccountIn = $apiArray[$i]['resAccountIn'];
                    $resAccountDesc1 = $apiArray[$i]['resAccountDesc1'];
                    $resAccountDesc2 = $apiArray[$i]['resAccountDesc2'];
                    $resAccountDesc3 = $apiArray[$i]['resAccountDesc3'];
                    $resAccountDesc4 = $apiArray[$i]['resAccountDesc4'];
                    $resAfterTranBalance = $apiArray[$i]['resAfterTranBalance'];

                    $sql_income_api_search = "SELECT COUNT(*) FROM js_income WHERE resAccountDesc2 LIKE '%".$resAccountDesc2."%' AND resAccountDesc3 LIKE '%".$resAccountDesc3."%' AND resAccountDesc4 LIKE '%".$resAccountDesc4."%'";
                    $sql_income_api_search = $sql_income_api_search." AND resAccountIn = '".$resAccountIn."' AND resAfterTranBalance = '".$resAfterTranBalance."' AND resAccountTrDate = '".$resAccountTrDate."' AND resAccountTrTime = '".$resAccountTrTime."';";

                    $cnt = $tradeapi->query_one($sql_income_api_search);

                    if($cnt <1){
                    $sql_income_insert = "INSERT INTO kkikda.js_income (resAccountDesc1, resAccountDesc2, resAccountDesc3, resAccountDesc4, resAccountIn, resAccountOut, resAccountTrDate, resAccountTrTime, resAfterTranBalance, complteYN)
                    VALUES('".$resAccountDesc1."', '".$resAccountDesc2."', '".$resAccountDesc3."', '".$resAccountDesc4."', '".$resAccountIn."', '".$resAccountOut."', '".$resAccountTrDate."', '".$resAccountTrTime."', '".$resAfterTranBalance."', 'N');
                    ";
                    $tradeapi->query_one($sql_income_insert);
                    }
                }
                // data 데이터
                //$tradeapi->error('049', __($sql_income_api_search)); //주문수량을 잔여수량 이하로 입력해주세요.
            } else {
                //$tradeapi->error('ff', __('qqqqq.')); //주문수량을 잔여수량 이하로 입력해주세요.
                $tradeapi->error('049', __('API 요청 실패'. $httpCode. '  //  '. $response)); //주문수량을 잔여수량 이하로 입력해주세요.

            }
            
            // 연결 종료
            curl_close($ch);

            /***
             *  ----------------- 여기까지
             */

            //api최신화중 완료되지 않은 내용이 있다는 전재로 진행
            
            $update_cnt_sql = "SELECT COUNT(*) FROM js_income WHERE js_income.complteYN='N';";
            $update_cnt = $tradeapi->query_one($update_cnt_sql);

            for ($i = 0; $i < $update_cnt; $i++) {
                //배열로 만들기
                $data = $currencies[$i];
                $valueList = [];
                foreach ($data as $value) {
                    $valueList[] = $value;
                }
                
                $txnid = $valueList[0];
                $userno = $valueList[1];
                $name = $valueList[2];
                $amount = $valueList[3];

                $result = $txnid."/".$userno."/".$name."/".$amount;
                
                //입금 내역확인
                $sql_income_search = "SELECT count(*) FROM js_income WHERE complteYN = 'N' AND js_income.resAccountDesc3 LIKE '%".$name."%' AND js_income.resAccountIn = '".$amount."';";
                $income_cnt = $tradeapi->query_one($sql_income_search);
                
                //입금 내역이 있으면 진행
                if($income_cnt>0){
                $currentDateTime = date('Y-m-d H:i:s');
                //income 업데이트
                $sql_income_search = "SELECT incomeIndex FROM js_income WHERE complteYN = 'N' AND js_income.resAccountDesc3 LIKE '%".$name."%' AND js_income.resAccountIn = '".$amount."' ORDER BY incomeIndex ASC LIMIT 1;";
                $incomeIndex = $tradeapi->query_one($sql_income_search);

                $sql_income_update = "UPDATE kkikda.js_income SET userno='".$userno."', complteYN='Y', txnindex='".$txnid."' WHERE incomeIndex='".$incomeIndex."';";
                $tradeapi->query_one($sql_income_update);

                //txn 업데이트
                $text = "자동 업데이트 imcomeIndex : ".$incomeIndex;
                $sql_txn_update = "UPDATE kkikda.js_exchange_wallet_txn SET txndate='".$currentDateTime."', status='D', msg='".$text."' WHERE txnid=".$txnid.";";
                $tradeapi->query_one($sql_txn_update);
                
                ///userno에 돈집어넣는 로직

                $sql_wallet_search = "SELECT confirmed FROM js_exchange_wallet WHERE userno = '".$userno."' AND symbol = 'KRW'";
                $confirmed = $tradeapi->query_one($sql_wallet_search);
                $confirmed = $confirmed + $amount;

                $sql_wallet_update = "UPDATE kkikda.js_exchange_wallet SET confirmed=".$confirmed." WHERE userno='".$userno."' AND symbol='KRW';";
                
                $tradeapi->query_one($sql_wallet_update);

                //$tradeapi->error('049', __($sql_wallet_update));
                }
            }
		}
	} 
}

$tradeapi->write_log('genIndex.php end.');