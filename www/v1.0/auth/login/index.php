<?php
// =====================================================
// api/www/v1.0/auth/login/index.php
// 로그인 (패스워드 없음)
// =====================================================

include dirname(__file__) . "/../../../lib/TradeApi.php";

/**
 * 간단 로그인 (이메일 또는 username만으로)
 */

// validate parameters
$login_id = checkEmpty(loadParam('login_id'), 'login_id'); // email 또는 username
$device_type = setDefault(loadParam('device_type'), 'mobile'); // mobile, tablet, web

$tradeapi->set_db_link('master');

// 사용자 찾기 (email 또는 username으로)
$user = $tradeapi->query_one("
    SELECT id, username, email, role, status, first_name, last_name 
    FROM GOS_users 
    WHERE (email = ? OR username = ?) 
    AND deleted_at IS NULL", 
    [$login_id, $login_id]
);

if(!$user) {
    // 로그인 실패 로그
    $tradeapi->insert("INSERT INTO GOS_user_logs (action_type, ip_address, success, error_message, created_at) VALUES ('login', ?, FALSE, 'User not found', NOW())", 
                     [$_SERVER['REMOTE_ADDR']]);
    $tradeapi->error(__('User not found'));
}

// 계정 상태 확인
if($user->status !== 'active') {
    $tradeapi->error(__('Account is not active. Status: ' . $user->status));
}

// 세션 토큰 생성
$session_token = bin2hex(random_bytes(32));
$expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));

// 기존 세션 비활성화
$tradeapi->query("UPDATE GOS_user_sessions SET is_active = FALSE WHERE user_id = ?", [$user->id]);

// 새 세션 생성
$device_info = json_encode([
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
]);

$session_id = $tradeapi->insert("
    INSERT INTO GOS_user_sessions (
        user_id, session_token, device_type, device_info, ip_address, 
        user_agent, expires_at, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())", 
    [$user->id, $session_token, $device_type, $device_info, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], $expires_at]
);

// 마지막 로그인 시간 업데이트
$tradeapi->query("UPDATE GOS_users SET last_login = NOW(), login_count = login_count + 1 WHERE id = ?", [$user->id]);

// 로그인 성공 로그
$tradeapi->insert("INSERT INTO GOS_user_logs (user_id, action_type, ip_address, success, created_at) VALUES (?, 'login', ?, TRUE, NOW())", 
                 [$user->id, $_SERVER['REMOTE_ADDR']]);

$tradeapi->success([
    'token' => $session_token,
    'user' => [
        'id' => $user->id,
        'username' => $user->username,
        'email' => $user->email,
        'role' => $user->role,
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'status' => $user->status
    ],
    'expires_at' => $expires_at
]);

?>
