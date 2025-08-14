<?php
// =====================================================
// api/www/v1.0/auth/login/index.php
// GosApi 사용한 로그인 (SimpleRestful 상속 버전)
// =====================================================

include dirname(__file__) . "/../../../lib/GosApi.php";

/**
 * 간단 로그인 - GosApi 버전 (SimpleRestful 상속)
 */

// validate parameters
$login_id = checkEmpty(loadParam('login_id'), 'login_id');
$device_type = setDefault(loadParam('device_type'), 'mobile');

// 디버그 정보 출력
$debug_info = [
    'gos_api_version' => 'v1.0 (SimpleRestful)',
    'received_login_id' => $login_id,
    'received_device_type' => $device_type,
    'inheritance' => 'SimpleRestful → GosApi',
    'db_connection' => 'Auto configured from SimpleRestful',
    'table_check' => 'GOS_users',
    'current_time' => date('Y-m-d H:i:s'),
    'compatibility' => 'TradeApi compatible'
];

$GLOBALS['gosapi']->success($debug_info, 'GOS API with SimpleRestful inheritance');

/*
// 실제 로그인 로직 (주석 처리)
$user = $GLOBALS['gosapi']->get_gos_user($login_id);

if (!$user) {
    $GLOBALS['gosapi']->log_gos_activity(null, 'login', false, 'User not found');
    $GLOBALS['gosapi']->error('User not found');
}

if ($user->status !== 'active') {
    $GLOBALS['gosapi']->log_gos_activity($user->id, 'login', false, 'Account not active');
    $GLOBALS['gosapi']->error('Account is not active. Status: ' . $user->status);
}

// 세션 토큰 생성
$session_token = $GLOBALS['gosapi']->create_gos_session($user->id, $device_type);

// 마지막 로그인 시간 업데이트
$GLOBALS['gosapi']->query("UPDATE GOS_users SET last_login = NOW(), login_count = login_count + 1 WHERE id = ?", [$user->id]);

// 로그인 성공 로그
$GLOBALS['gosapi']->log_gos_activity($user->id, 'login', true);

// 성공 응답
$GLOBALS['gosapi']->success([
    'token' => $session_token,
    'user' => [
        'id' => $user->id,
        'username' => $user->username,
        'email' => $user->email,
        'role' => $user->role,
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'status' => $user->status
    ]
], 'Login successful');
*/

?>