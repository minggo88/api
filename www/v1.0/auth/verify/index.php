<?php
// =====================================================
// api/www/v1.0/auth/verify/index.php
// 로그인 세션 확인
// =====================================================

include dirname(__file__) . "/../../lib/TradeApi.php";

/**
 * 로그인 세션 확인
 */

// validate parameters
$token = checkEmpty(loadParam('token'), 'token');

$tradeapi->set_db_link('slave');

// 세션 확인
$session = $tradeapi->query_one("
    SELECT s.*, u.id as user_id, u.username, u.email, u.role, u.status, u.first_name, u.last_name
    FROM GOS_user_sessions s
    JOIN GOS_users u ON s.user_id = u.id
    WHERE s.session_token = ? 
    AND s.is_active = TRUE 
    AND s.expires_at > NOW()
    AND u.status = 'active'
    AND u.deleted_at IS NULL", 
    [$token]
);

if(!$session) {
    $tradeapi->error(__('Invalid or expired session'), 401);
}

// 마지막 활동 시간 업데이트
$tradeapi->query("UPDATE GOS_user_sessions SET last_activity = NOW() WHERE session_token = ?", [$token]);

$tradeapi->success([
    'valid' => true,
    'user' => [
        'id' => $session->user_id,
        'username' => $session->username,
        'email' => $session->email,
        'role' => $session->role,
        'first_name' => $session->first_name,
        'last_name' => $session->last_name,
        'status' => $session->status
    ],
    'session' => [
        'expires_at' => $session->expires_at,
        'last_activity' => $session->last_activity
    ]
]);

?>