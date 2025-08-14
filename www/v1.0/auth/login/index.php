<?php
// =====================================================
// api/www/v1.0/auth/login/index.php
// GosApi 사용한 로그인 (토큰 없음)
// =====================================================

include dirname(__file__) . "/../../../lib/GosApi.php";

/**
 * 간단 로그인 - GosApi 버전 (토큰 없음)
 */
/*
// validate parameters
$login_id = checkEmpty(loadParam('login_id'), 'login_id');
$device_type = setDefault(loadParam('device_type'), 'mobile');

// 사용자 찾기
$user = $GLOBALS['gosapi']->get_gos_user($login_id);

if(!$user) {
    // 로그인 실패 로그
    $GLOBALS['gosapi']->log_gos_activity(null, 'login', false, 'User not found');
    $GLOBALS['gosapi']->error('User not found');
}

// 계정 상태 확인
if($user->status !== 'active') {
    $GLOBALS['gosapi']->log_gos_activity($user->id, 'login', false, 'Account not active');
    $GLOBALS['gosapi']->error('Account is not active. Status: ' . $user->status);
}

// 마지막 로그인 시간 업데이트
$GLOBALS['gosapi']->query("UPDATE GOS_users SET last_login = NOW(), login_count = login_count + 1 WHERE id = ?", [$user->id]);

// 로그인 성공 로그
$GLOBALS['gosapi']->log_gos_activity($user->id, 'login', true);

$GLOBALS['gosapi']->success([
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

// 임시 디버그 파일
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo json_encode([
    'success' => true,
    'message' => 'Basic PHP working',
    'timestamp' => date('Y-m-d H:i:s'),
    'post_data' => $_POST,
    'get_data' => $_GET
]);

?>