<?php
// =====================================================
// api/www/v1.0/auth/logout/index.php
// 간단한 로그아웃 (토큰 없는 버전)
// =====================================================

// CORS 헤더 설정

// OPTIONS 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// GosApi 포함
require_once __DIR__ . '/../../../lib/GosApi.php';

try {
    // 파라미터 검증 (사용자 ID 또는 사용자명)
    $user_id = loadParam('user_id');
    $username = loadParam('username');
    
    $logout_user_id = null;
    $logout_identifier = null;

    if (!empty($user_id)) {
        // 사용자 ID로 로그아웃
        $logout_user_id = $user_id;
        $logout_identifier = "user_id: {$user_id}";
    } elseif (!empty($username)) {
        // 사용자명으로 로그아웃
        $user = $GLOBALS['gosapi']->get_gos_user($username);
        if ($user) {
            $logout_user_id = $user->id;
            $logout_identifier = "username: {$username}";
        } else {
            $GLOBALS['gosapi']->error('User not found', 404);
        }
    } else {
        $GLOBALS['gosapi']->error('user_id or username required for logout');
    }

    // 로그아웃 로그 기록
    $GLOBALS['gosapi']->log_gos_activity(
        $logout_user_id, 
        'logout', 
        true, 
        "Logout via {$logout_identifier}"
    );
    
    // 성공 응답
    $response_data = [
        'user_id' => $logout_user_id,
        'logout_identifier' => $logout_identifier,
        'logout_time' => date('Y-m-d H:i:s'),
        'message' => 'User logged out successfully'
    ];
    
    $GLOBALS['gosapi']->success($response_data, __('Logout successful'));

} catch (Exception $e) {
    error_log('Logout API Error: ' . $e->getMessage());
    
    // 실패 로그 (사용자 ID를 알 수 있는 경우에만)
    if (isset($logout_user_id) && $logout_user_id) {
        $GLOBALS['gosapi']->log_gos_activity(
            $logout_user_id, 
            'logout', 
            false, 
            'Logout failed: ' . $e->getMessage()
        );
    }
    
    $GLOBALS['gosapi']->error('Internal server error', 500);
}

?>