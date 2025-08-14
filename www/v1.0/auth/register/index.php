<?php
// =====================================================
// api/www/v1.0/auth/register/index.php
// 회원가입 (패스워드 없음)
// =====================================================

include dirname(__file__) . "/../../../lib/TradeApi.php";

/**
 * 회원가입 (간단 버전)
 */

// validate parameters
$username = checkEmpty(loadParam('username'), 'username');
$email = checkEmpty(loadParam('email'), 'email');
$first_name = setDefault(loadParam('first_name'), '');
$last_name = setDefault(loadParam('last_name'), '');
$phone = setDefault(loadParam('phone'), '');
$role = setDefault(loadParam('role'), 'student'); // student, teacher

// 이메일 형식 검증
if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $tradeapi->error(__('Invalid email format'));
}

$tradeapi->set_db_link('master');

// 중복 확인
$existing = $tradeapi->query_one("SELECT id FROM GOS_users WHERE email = ? OR username = ? AND deleted_at IS NULL", [$email, $username]);
if($existing) {
    $tradeapi->error(__('Email or username already exists'));
}

// 사용자 생성
$sql = "INSERT INTO GOS_users (
            username, email, role, first_name, last_name, phone, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())";

$params = [$username, $email, $role, $first_name, $last_name, $phone];

$user_id = $tradeapi->insert($sql, $params);

if($user_id) {
    // 회원가입 로그
    $tradeapi->insert("INSERT INTO GOS_user_logs (user_id, action_type, ip_address, success, created_at) VALUES (?, 'register', ?, TRUE, NOW())", 
                     [$user_id, $_SERVER['REMOTE_ADDR']]);
    
    $tradeapi->success([
        'user_id' => $user_id,
        'message' => __('Registration successful')
    ]);
} else {
    $tradeapi->error(__('Registration failed'));
}

?>