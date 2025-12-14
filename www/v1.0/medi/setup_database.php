<?php
/**
 * 데이터베이스 테이블 생성 스크립트
 * 이 파일을 한 번만 실행하여 모든 테이블을 생성합니다.
 * 
 * 실행 방법: 브라우저에서 setup_database.php 접속
 */

require_once '/../../lib/MediConfig.php';

// 연결 테스트
try {
    $pdo = getDBConnection();
    echo "<h2>✓ 데이터베이스 연결 성공!</h2>";
    echo "<p>Host: " . DB_HOST . "</p>";
    echo "<p>Database: " . DB_NAME . "</p>";
    echo "<hr>";
} catch (Exception $e) {
    die("<h2>✗ 연결 실패</h2><p>" . $e->getMessage() . "</p>");
}

// 테이블 생성 함수
function createTable($pdo, $tableName, $sql) {
    try {
        $pdo->exec($sql);
        echo "✓ <strong>{$tableName}</strong> 테이블 생성 완료<br>";
        return true;
    } catch (PDOException $e) {
        echo "✗ <strong>{$tableName}</strong> 테이블 생성 실패: " . $e->getMessage() . "<br>";
        return false;
    }
}

echo "<h2>테이블 생성 시작...</h2>";

// 1. users 테이블 (환자 정보)
$sql_users = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL COMMENT '환자 이름',
    phone VARCHAR(20) NOT NULL COMMENT '전화번호',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일시',
    INDEX idx_phone (phone),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='환자 기본 정보';
";
createTable($pdo, 'users', $sql_users);

// 2. bookings 테이블 (접수)
$sql_bookings = "
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_number VARCHAR(20) UNIQUE NOT NULL COMMENT '접수번호 (예: 2024100401)',
    user_id INT NOT NULL COMMENT '환자 ID',
    google_form_response_id VARCHAR(255) DEFAULT NULL COMMENT '구글폼 응답 ID',
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending' COMMENT '상태',
    notes TEXT DEFAULT NULL COMMENT '메모/특이사항',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '접수일시',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일시',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_booking_number (booking_number),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='진료 접수 정보';
";
createTable($pdo, 'bookings', $sql_bookings);

// 3. consents 테이블 (동의내역)
$sql_consents = "
CREATE TABLE IF NOT EXISTS consents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT '환자 ID',
    consent_type ENUM('privacy', 'third_party', 'sensitive', 'terms', 'marketing') NOT NULL COMMENT '동의 유형',
    consent_version VARCHAR(20) DEFAULT '1.0' COMMENT '약관 버전',
    agreed BOOLEAN DEFAULT FALSE COMMENT '동의 여부',
    agreed_at TIMESTAMP NULL DEFAULT NULL COMMENT '동의 시각',
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP 주소',
    user_agent TEXT DEFAULT NULL COMMENT '브라우저 정보',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_consent_type (consent_type),
    INDEX idx_agreed_at (agreed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='약관 동의 기록';
";
createTable($pdo, 'consents', $sql_consents);

// 4. prescriptions 테이블 (처방전)
$sql_prescriptions = "
CREATE TABLE IF NOT EXISTS prescriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL COMMENT '접수 ID',
    diagnosis VARCHAR(500) NOT NULL COMMENT '진단명',
    prescription_details TEXT NOT NULL COMMENT '처방 내용',
    amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT '금액',
    prescription_type ENUM('prescription', 'medicine') DEFAULT 'medicine' COMMENT '유형: 처방전/처방약',
    pdf_url VARCHAR(255) DEFAULT NULL COMMENT 'PDF 파일 경로',
    qr_code VARCHAR(255) DEFAULT NULL COMMENT 'QR 코드 이미지 경로',
    notes TEXT DEFAULT NULL COMMENT '메모',
    created_by INT DEFAULT NULL COMMENT '발급한 관리자 ID',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '발급일시',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일시',
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    INDEX idx_booking_id (booking_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='처방전 정보';
";
createTable($pdo, 'prescriptions', $sql_prescriptions);

// 5. payments 테이블 (결제)
$sql_payments = "
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL COMMENT '접수 ID',
    amount DECIMAL(10, 2) NOT NULL COMMENT '결제 금액',
    payment_method VARCHAR(50) DEFAULT NULL COMMENT '결제 수단',
    pg_transaction_id VARCHAR(255) DEFAULT NULL COMMENT 'PG 거래번호',
    merchant_uid VARCHAR(255) DEFAULT NULL COMMENT '가맹점 주문번호',
    imp_uid VARCHAR(255) DEFAULT NULL COMMENT '아임포트 고유번호',
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending' COMMENT '결제 상태',
    paid_at TIMESTAMP NULL DEFAULT NULL COMMENT '결제 완료 시각',
    refunded_at TIMESTAMP NULL DEFAULT NULL COMMENT '환불 시각',
    refund_reason TEXT DEFAULT NULL COMMENT '환불 사유',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일시',
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    INDEX idx_booking_id (booking_id),
    INDEX idx_status (status),
    INDEX idx_pg_transaction_id (pg_transaction_id),
    INDEX idx_paid_at (paid_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='결제 정보';
";
createTable($pdo, 'payments', $sql_payments);

// 6. admins 테이블 (관리자)
$sql_admins = "
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL COMMENT '관리자 아이디',
    password VARCHAR(255) NOT NULL COMMENT '비밀번호 (해시)',
    name VARCHAR(50) NOT NULL COMMENT '관리자 이름',
    email VARCHAR(100) DEFAULT NULL COMMENT '이메일',
    role ENUM('super_admin', 'admin', 'staff') DEFAULT 'admin' COMMENT '권한',
    is_active BOOLEAN DEFAULT TRUE COMMENT '활성 상태',
    last_login TIMESTAMP NULL DEFAULT NULL COMMENT '마지막 로그인',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일시',
    INDEX idx_username (username),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='관리자 계정';
";
createTable($pdo, 'admins', $sql_admins);

// 7. notifications 테이블 (알림 내역)
$sql_notifications = "
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL COMMENT '접수 ID',
    notification_type ENUM('sms', 'email') NOT NULL COMMENT '알림 유형',
    recipient VARCHAR(100) NOT NULL COMMENT '수신자 (전화번호 or 이메일)',
    message TEXT NOT NULL COMMENT '메시지 내용',
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending' COMMENT '발송 상태',
    sent_at TIMESTAMP NULL DEFAULT NULL COMMENT '발송 시각',
    error_message TEXT DEFAULT NULL COMMENT '에러 메시지',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    INDEX idx_booking_id (booking_id),
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='알림 발송 내역';
";
createTable($pdo, 'notifications', $sql_notifications);

// 8. access_logs 테이블 (접근 로그)
$sql_logs = "
CREATE TABLE IF NOT EXISTS access_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL COMMENT '환자 ID (로그인 시)',
    admin_id INT DEFAULT NULL COMMENT '관리자 ID (로그인 시)',
    action VARCHAR(100) NOT NULL COMMENT '수행 동작',
    page VARCHAR(255) DEFAULT NULL COMMENT '접근 페이지',
    ip_address VARCHAR(45) NOT NULL COMMENT 'IP 주소',
    user_agent TEXT DEFAULT NULL COMMENT '브라우저 정보',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '접근 시각',
    INDEX idx_user_id (user_id),
    INDEX idx_admin_id (admin_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='접근 로그';
";
createTable($pdo, 'access_logs', $sql_logs);

echo "<hr>";
echo "<h2>✓ 모든 테이블 생성 완료!</h2>";

// 기본 관리자 계정 생성
echo "<h3>기본 관리자 계정 생성</h3>";
try {
    $admin_username = 'admin';
    $admin_password = 'admin1234!'; // 나중에 꼭 변경하세요!
    $admin_name = '시스템 관리자';
    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT INTO admins (username, password, name, role) 
        VALUES (?, ?, ?, 'super_admin')
        ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$admin_username, $hashed_password, $admin_name]);
    
    echo "<p>✓ 기본 관리자 계정 생성 완료</p>";
    echo "<p><strong>아이디:</strong> admin<br>";
    echo "<strong>비밀번호:</strong> admin1234! (반드시 변경하세요!)</p>";
    
} catch (PDOException $e) {
    echo "<p>✗ 관리자 계정 생성 실패: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>생성된 테이블 목록:</h3>";
echo "<ol>";
echo "<li>users - 환자 기본 정보</li>";
echo "<li>bookings - 진료 접수</li>";
echo "<li>consents - 약관 동의 기록</li>";
echo "<li>prescriptions - 처방전</li>";
echo "<li>payments - 결제 정보</li>";
echo "<li>admins - 관리자 계정</li>";
echo "<li>notifications - 알림 발송 내역</li>";
echo "<li>access_logs - 접근 로그</li>";
echo "</ol>";

echo "<hr>";
echo "<p><strong>주의:</strong> 이 파일(setup_database.php)은 보안상 삭제하거나 접근을 제한하세요!</p>";
echo "<p><a href='index.php'>메인 페이지로 이동</a></p>";
?>