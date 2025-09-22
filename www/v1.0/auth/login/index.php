<?php
// =====================================================
// /home/ubuntu/www/api/www/v1.0/auth/login/index.php
// =====================================================

// OPTIONS 요청 처리 (서버 레벨 CORS 설정 사용)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// GosApi 포함 - 올바른 경로로 수정
require_once __DIR__ . '/../../../lib/GosApi.php';

try {
    // POST 요청만 허용
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $GLOBALS['gosapi']->error('Only POST method allowed', 405);
    }
    
    // 파라미터 받기 (패스워드 필드는 받되 검증하지 않음)
    $login_id = checkEmpty(loadParam('login_id'), 'login_id');
    $password = loadParam('password', ''); // 패스워드는 선택적
    
    // 사용자 조회
    $user = $GLOBALS['gosapi']->get_gos_user($login_id);
    
    if (!$user) {
        // 로그인 실패 로그
        $GLOBALS['gosapi']->log_gos_activity(null, 'login', false, 'User not found');
        $GLOBALS['gosapi']->error('Invalid login credentials', 401);
    }
    
    // 계정 상태 확인
    if ($user->status !== 'active') {
        $GLOBALS['gosapi']->log_gos_activity($user->id, 'login', false, 'Account not active');
        $GLOBALS['gosapi']->error('Account is not active', 403);
    }
    
    // 로그인 성공 - 로그인 횟수 증가 및 마지막 로그인 시간 업데이트
    /*$update_sql = "UPDATE GOS_users SET 
                   login_count = login_count + 1, 
                   last_login = NOW() 
                   WHERE id = ?";
    $GLOBALS['gosapi']->query($update_sql, [$user->id]);*/
    
    // 로그인 성공 로그
    $GLOBALS['gosapi']->log_gos_activity($user->id, 'login', true);
    
    // 응답 데이터 준비
    $response_data = [
        'user' => [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'nickname' => $user->nickname,
            'profile_image' => $user->profile_image,
            'language_preference' => $user->language_preference,
            'login_count' => $user->login_count + 1
        ]
    ];
    
    $GLOBALS['gosapi']->success($response_data, 'Login successful');
    
} catch (Exception $e) {
    error_log('Login API Error: ' . $e->getMessage());
    $GLOBALS['gosapi']->error('Internal server error', 500);
}
?>