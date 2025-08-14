<?php
// =====================================================
// /home/ubuntu/www/api/www/v1.0/auth/login/index.php
// =====================================================

// CORS 헤더 설정
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// OPTIONS 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// GosApi 포함 - 올바른 경로로 수정
require_once __DIR__ . '/../../lib/GosApi.php';

try {
    // POST 요청만 허용
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $GLOBALS['gosapi']->error('Only POST method allowed', 405);
    }
    
    // 파라미터 받기
    $login_id = checkEmpty(loadParam('login_id'), 'login_id');
    $password = checkEmpty(loadParam('password'), 'password');
    
    // 사용자 조회
    $user = $GLOBALS['gosapi']->get_gos_user($login_id);
    
    if (!$user) {
        // 로그인 실패 로그
        $GLOBALS['gosapi']->log_gos_activity(null, 'LOGIN_FAILED', false, 'User not found');
        $GLOBALS['gosapi']->error('Invalid login credentials', 401);
    }
    
    // 비밀번호 검증 (실제로는 password_verify 사용해야 함)
    if (!password_verify($password, $user->password_hash)) {
        // 로그인 실패 로그
        $GLOBALS['gosapi']->log_gos_activity($user->id, 'LOGIN_FAILED', false, 'Invalid password');
        $GLOBALS['gosapi']->error('Invalid login credentials', 401);
    }
    
    // 계정 상태 확인
    if ($user->status !== 'active') {
        $GLOBALS['gosapi']->log_gos_activity($user->id, 'LOGIN_FAILED', false, 'Account not active');
        $GLOBALS['gosapi']->error('Account is not active', 403);
    }
    
    // 로그인 성공 로그
    $GLOBALS['gosapi']->log_gos_activity($user->id, 'LOGIN_SUCCESS', true);
    
    // 응답 데이터 준비
    $response_data = [
        'user' => [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name
        ]
    ];
    
    $GLOBALS['gosapi']->success($response_data, 'Login successful');
    
} catch (Exception $e) {
    error_log('Login API Error: ' . $e->getMessage());
    $GLOBALS['gosapi']->error('Internal server error', 500);
}
?>