<?php
// =====================================================
// api/www/v1.0/auth/logout/index.php
// 로그아웃
// =====================================================

include dirname(__file__) . "/../../lib/TradeApi.php";

/**
 * 로그아웃
 */

// validate parameters
$token = checkEmpty(loadParam('token'), 'token');

$tradeapi->set_db_link('master');

// 세션 비활성화
$result = $tradeapi->query("UPDATE GOS_user_sessions SET is_active = FALSE WHERE session_token = ?", [$token]);

if($result) {
    // 사용자 정보 가져오기 (로그용)
    $session = $tradeapi->query_one("SELECT user_id FROM GOS_user_sessions WHERE session_token = ?", [$token]);
    
    if($session) {
        // 로그아웃 로그
        $tradeapi->insert("INSERT INTO GOS_user_logs (user_id, action_type, ip_address, success, created_at) VALUES (?, 'logout', ?, TRUE, NOW())", 
                         [$session->user_id, $_SERVER['REMOTE_ADDR']]);
    }
    
    $tradeapi->success(['message' => __('Logout successful')]);
} else {
    $tradeapi->error(__('Logout failed'));
}

?>