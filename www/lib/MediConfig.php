<?php
/**
 * 데이터베이스 연결 설정
 * 파일 위치: /includes/config.php
 */

// 데이터베이스 연결 정보
define('DB_HOST', 'kkikda-dev.catyypkt8dey.ap-northeast-2.rds.amazonaws.com');
define('DB_NAME', 'telemedicine'); // 데이터베이스 이름
define('DB_USER', 'kkikda');
define('DB_PASS', 'KKe8IuK28Due82A');
define('DB_CHARSET', 'utf8mb4');

// 타임존 설정
date_default_timezone_set('Asia/Seoul');

// 에러 표시 설정 (개발 환경)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 세션 보안 설정
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // HTTPS 사용시 1로 변경

/**
 * PDO 데이터베이스 연결 함수
 * @return PDO
 */
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
        
    } catch (PDOException $e) {
        // 실제 운영 환경에서는 에러를 로그 파일에 기록하고 일반적인 메시지만 표시
        die("데이터베이스 연결 실패: " . $e->getMessage());
    }
}

/**
 * 입력값 정제 함수
 * @param string $data
 * @return string
 */
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * 전화번호 형식 검증
 * @param string $phone
 * @return bool
 */
function validatePhone($phone) {
    return preg_match('/^010-\d{4}-\d{4}$/', $phone);
}

/**
 * 이름 형식 검증 (한글 2-10자)
 * @param string $name
 * @return bool
 */
function validateName($name) {
    return preg_match('/^[가-힣]{2,10}$/', $name);
}
?>