<?php
// =====================================================
// api/www/v1.0/auth/verify/index.php
// 간단한 사용자 확인 (토큰 없는 버전)
// =====================================================

// CORS 헤더 설정
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

// OPTIONS 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// GosApi 포함
require_once __DIR__ . '/../../../lib/GosApi.php';

try {
    // 파라미터 검증
    $user_id = loadParam('user_id');
    $username = loadParam('username');
    $email = loadParam('email');

    $user_data = null;
    $verification_method = 'unknown';

    if (!empty($user_id)) {
        // 사용자 ID로 확인
        $user_sql = "SELECT id, username, email, role, status, first_name, last_name, 
                            nickname, profile_image, language_preference, login_count, last_login
                     FROM GOS_users 
                     WHERE id = ? AND status = 'active' AND deleted_at IS NULL";
        
        $user_data = $GLOBALS['gosapi']->query_fetch_object($user_sql, [$user_id]);
        $verification_method = 'user_id';
        
    } elseif (!empty($username)) {
        // 사용자명으로 확인
        $user_data = $GLOBALS['gosapi']->get_gos_user($username);
        $verification_method = 'username';
        
    } elseif (!empty($email)) {
        // 이메일로 확인
        $user_data = $GLOBALS['gosapi']->get_gos_user($email);
        $verification_method = 'email';
        
    } else {
        $GLOBALS['gosapi']->error('user_id, username, or email required for verification');
    }

    if (!$user_data) {
        $GLOBALS['gosapi']->error(__('User not found or inactive'), 404);
    }

    if ($user_data->status !== 'active') {
        $GLOBALS['gosapi']->error(__('User account is not active'), 403);
    }

    // 검증 성공 로그
    $GLOBALS['gosapi']->log_gos_activity(
        $user_data->id, 
        'verify', 
        true, 
        "User verified via {$verification_method}"
    );

    // 성공 응답
    $response_data = [
        'valid' => true,
        'verification_method' => $verification_method,
        'user' => [
            'id' => (int)$user_data->id,
            'username' => $user_data->username,
            'email' => $user_data->email,
            'role' => $user_data->role,
            'first_name' => $user_data->first_name,
            'last_name' => $user_data->last_name,
            'nickname' => $user_data->nickname,
            'profile_image' => $user_data->profile_image,
            'language_preference' => $user_data->language_preference,
            'login_count' => (int)$user_data->login_count,
            'status' => $user_data->status,
            'last_login' => $user_data->last_login
        ]
    ];

    $GLOBALS['gosapi']->success($response_data, __('User verification successful'));

} catch (Exception $e) {
    error_log('Verify API Error: ' . $e->getMessage());
    
    // 실패 로그 (사용자 ID를 알 수 있는 경우에만)
    if (isset($user_data) && $user_data && $user_data->id) {
        $GLOBALS['gosapi']->log_gos_activity(
            $user_data->id, 
            'verify', 
            false, 
            'Verification failed: ' . $e->getMessage()
        );
    }
    
    $GLOBALS['gosapi']->error('Internal server error', 500);
}

?>