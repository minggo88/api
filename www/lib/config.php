<?php
if (isset($_SERVER["SERVER_ADDR"])) {
    // 서버아이피보정
    $_SERVER["SERVER_ADDR"] = isset($_SERVER["HTTP_X_SERVER_ADDRESS"]) && $_SERVER["HTTP_X_SERVER_ADDRESS"] ? $_SERVER["HTTP_X_SERVER_ADDRESS"] : $_SERVER["SERVER_ADDR"];
    // 접속자아이피보정
    $_SERVER["REMOTE_ADDR"] = isset($_SERVER["HTTP_X_FORWARDED_FOR"]) && $_SERVER["HTTP_X_FORWARDED_FOR"] ?  explode(',', $_SERVER["HTTP_X_FORWARDED_FOR"])[0] : $_SERVER["SERVER_ADDR"];
}

if (!defined('__APP_NAME__')) {
    define('__APP_NAME__', 'TRADE');
    define('__APP_ID__', 'KKIKDA'); // js_app.app_id 값과 같습니다.
    define('__APP_NO__', '11'); // js_app.app_no 값과 같습니다.
}
if (!defined('__API_RUNMODE__')) {
    $runmode = 'live';
    if (isset($_SERVER['HTTP_HOST'])) { // run web server
        if (strpos($_SERVER['HTTP_HOST'], 'loc.') !== false || strpos($_SERVER['HTTP_HOST'], 'src.') !== false) {
            $runmode = 'loc';
        }
        if (strpos($_SERVER['HTTP_HOST'], 'dev.') !== false) {
            $runmode = 'dev';
        }
        if (strpos($_SERVER['HTTP_HOST'], 'stage.') !== false) {
            $runmode = 'stage';
        }
        if (strpos($_SERVER['HTTP_HOST'], '.local') !== false) {
            $runmode = 'loc';
        }
    } else { // run cli
        // var_dump(PHP_OS); exit;
        if (strtolower(PHP_OS) == 'winnt') {
            $ip = gethostbyname(gethostname());
            // var_dump(PHP_OS, $ip); //exit;
        }
        if (strtolower(PHP_OS) == 'linux') {
            exec("/sbin/ifconfig", $ips);
            $ips = implode('', $ips);
            if (strpos($ips, 'eth0') !== false) {
                exec("/sbin/ifconfig eth0 | fgrep -i inet | grep \"inet \" | awk '{print $2}'", $ip);
            }
            if (strpos($ips, 'enp0s3') !== false) { // for aws ec2
                exec("/sbin/ifconfig enp0s3 | fgrep -i inet | grep \"inet \" | awk '{print $2}'", $ip);
            }
            if (strpos($ips, 'ens5') !== false) { // for aws ec2
                exec("/sbin/ifconfig ens5 | fgrep -i inet | grep \"inet \" | awk '{print $2}'", $ip);
            }
            if (empty($ip)) {
                $ip = '127.0.0.1';
            } else {
                $ip = $ip[0];
            }
        }
        if (!$ip || $ip == '127.0.0.1' || strpos($ip, '192.168.') !== false) {
            $runmode = 'loc';
        }
        if (($ip == '127.0.0.1' || strpos($ip, '192.168.0') !== false) && isset($_SERVER['USER'])  && ($_SERVER['USER'] == 'ubuntu' || $_SERVER['USER'] == 'root')) {
            $runmode = 'dev';
        }
        if ($ip == '10.10.2.157') {
            $runmode = 'stage';
        }
    }
    define('__API_RUNMODE__', $runmode);
    // var_dump(__API_RUNMODE__);exit;
}

if (!defined('__DB_INFO__')) {
    switch (__API_RUNMODE__) {
        case 'loc':
            $_db_info = array(
                'master' => array(
                    'host' => 'kkikda-dev.catyypkt8dey.ap-northeast-2.rds.amazonaws.com',
                    'username' => 'kkikda',
                    'password' => 'KKe8IuK28Due82A',
                    'charset' => 'utf8mb4',
                    'database' => 'kkikda'
                ),
                'slave' => array(
                    array(
                        'host' => 'kkikda-dev.catyypkt8dey.ap-northeast-2.rds.amazonaws.com',
                        'username' => 'kkikda',
                        'password' => 'KKe8IuK28Due82A',
                        'charset' => 'utf8mb4',
                        'database' => 'kkikda'
                    )
                )
            );
            $_memcache_info = array(
                'host' => '127.0.0.1',
                'port' => '11211',
            );
            $_google_drive_target_folderid = '1BWHSK6ofmXWi6kUgewJlGJ-T8psue72y';
            $_google_drive_credentialsFile = 'google_drive_credentials_dev.json';
            $_twitter_api = array(
                'CONSUMER_KEY' => 'WZzjf9mUcmxUK0m1oHNZZZkQH',
                'CONSUMER_SECRET' => 'ww4XvC9tg9DUrLRv9Wujr7SepMTOTqdgqE6MQFh193Oh4I4xaL',
                'ACCESS_TOKEN' => '1458659640611581952-2lgGtgUeXGvlsiR1pNGdKokhVjQQHN',
                'ACCESS_TOKEN_SECRET' => '0eJEwaAmtWGdVdqmqBldBjjZKZQ0L1Li5ZL8vbZXfjKYH',
            );
            $_gmail_account = array(
                'smtp_username' => 'info.teaplate@gmail.com',
                'smtp_password' => 'uzhdrdcexqhqgwdj'
            );
            break;
        case 'dev':
            $_db_info = array(
                'master' => array(
                    'host' => 'kkikda-dev.catyypkt8dey.ap-northeast-2.rds.amazonaws.com',
                    'username' => 'kkikda',
                    'password' => 'KKe8IuK28Due82A',
                    'charset' => 'utf8mb4',
                    'database' => 'kkikda'
                ),
                'slave' => array(
                    array(
                        'host' => 'kkikda-dev.catyypkt8dey.ap-northeast-2.rds.amazonaws.com',
                        'username' => 'kkikda',
                        'password' => 'KKe8IuK28Due82A',
                        'charset' => 'utf8mb4',
                        'database' => 'kkikda'
                    )
                )
            );
            $_memcache_info = array(
                'host' => 'kkikdacache-dev.a12ygy.cfg.apn2.cache.amazonaws.com',
                'port' => '11211',
            );
            $_google_drive_target_folderid = '1BWHSK6ofmXWi6kUgewJlGJ-T8psue72y';
            $_google_drive_credentialsFile = 'google_drive_credentials_dev.json';
            $_twitter_api = array(
                'CONSUMER_KEY' => 'WZzjf9mUcmxUK0m1oHNZZZkQH',
                'CONSUMER_SECRET' => 'ww4XvC9tg9DUrLRv9Wujr7SepMTOTqdgqE6MQFh193Oh4I4xaL',
                'ACCESS_TOKEN' => '1458659640611581952-2lgGtgUeXGvlsiR1pNGdKokhVjQQHN',
                'ACCESS_TOKEN_SECRET' => '0eJEwaAmtWGdVdqmqBldBjjZKZQ0L1Li5ZL8vbZXfjKYH',
            );
            $_gmail_account = array(
                'smtp_username' => 'info.teaplate@gmail.com',
                'smtp_password' => 'uzhdrdcexqhqgwdj'
            );
            break;
        case 'live':
            $_db_info = array(
                'master' => array(
                    'host' => 'kkikda.catyypkt8dey.ap-northeast-2.rds.amazonaws.com',
                    'username' => 'kkikda',
                    'password' => 'KKe8IuK28Due82A',
                    'charset' => 'utf8mb4',
                    'database' => 'kkikda'
                ),
                'slave' => array(
                    array(
                        'host' => 'kkikda.catyypkt8dey.ap-northeast-2.rds.amazonaws.com',
                        'username' => 'kkikda',
                        'password' => 'KKe8IuK28Due82A',
                        'charset' => 'utf8mb4',
                        'database' => 'kkikda'
                    )
                )
            );
            $_memcache_info = array(
                'host' => 'kkikdacache.a12ygy.cfg.apn2.cache.amazonaws.com',
                'port' => '11211',
            );
            $_google_drive_target_folderid = '1lPFPY_0F7eG29HguJvDW3t973bARNs2F'; // 라이브용 계정
            $_google_drive_credentialsFile = 'google_drive_credentials_live.json'; // 라이브용 계정 
            $_twitter_api = array(
                'CONSUMER_KEY' => '',
                'CONSUMER_SECRET' => '',
                'ACCESS_TOKEN' => '',
                'ACCESS_TOKEN_SECRET' => '',
            );
            $_gmail_account = array(
                'smtp_username' => 'teaplat.info@gmail.cm',
                'smtp_password' => 'rwellfuyltisynch'
            );
            break;
    }

    define('__DB_INFO__', $_db_info);
    define('__MEMCACHE_INFO__', $_memcache_info);

    // google drive
    define('__GOOGLE_DRIVE_TARGET_FOLDERID__', $_google_drive_target_folderid);
    define('__GOOGLE_DRIVE_CREDENTIALSFILE__', $_google_drive_credentialsFile);

    // SMTP - gmail
    define('__GOOGLE_GMAIL_USERNAME__', $_gmail_account['smtp_username']);
    define('__GOOGLE_GMAIL_PASSWORD__', $_gmail_account['smtp_password']);

    // twitter app
    define('CONSUMER_KEY', $_twitter_api['CONSUMER_KEY']);
    define('CONSUMER_SECRET', $_twitter_api['CONSUMER_SECRET']);
    define('ACCESS_TOKEN', $_twitter_api['ACCESS_TOKEN']);
    define('ACCESS_TOKEN_SECRET', $_twitter_api['ACCESS_TOKEN_SECRET']);
    
    // Social Login
    define('SOCIAL_LOGIN_KAKAO_API_KEY', '');
    define('SOCIAL_LOGIN_KAKAO_REDIRECT_URI', 'https://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'www.kmcse.com') . '/v1.0/socialLogin/redirect_uri.php?socail_type=kakao');

    // support language
    define('SUPPORT_LANGUAGE', array('ko','kr','en','zh','cn'));

}
