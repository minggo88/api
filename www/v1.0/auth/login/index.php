<?php
// =====================================================
// /home/ubuntu/www/api/www/v1.0/auth/login/index.php
// =====================================================

// CORS 헤더 설정 (중복 방지)
if (!headers_sent()) {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
}

// OPTIONS 요청 처리
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
    
    // 파라미터 받기 (FormData 또는 JSON 모두 지원)
    $login_id = '';
    $password = '';
    
    // JSON 데이터 확인
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if ($data) {
        // JSON 데이터가 있는 경우
        $login_id = $data['login_id'] ?? '';
        $password = $data['password'] ?? '';
    } else {
        // FormData 또는 POST 데이터 사용
        $login_id = $_POST['login_id'] ?? '';
        $password = $_POST['password'] ?? '';
    }
    
    // 로그인 ID 검증
    $login_id = checkEmpty($login_id, 'login_id');
    
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
    $update_sql = "UPDATE GOS_users SET 
                   login_count = login_count + 1, 
                   last_login = NOW() 
                   WHERE id = ?";
    $GLOBALS['gosapi']->query($update_sql, [$user->id]);
    
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