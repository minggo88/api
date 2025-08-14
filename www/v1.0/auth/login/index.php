<?php
// =====================================================
// /home/ubuntu/www/api/www/v1.0/auth/login/index.php
// 자동 회원가입 기능이 포함된 로그인 API
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
    $password = loadParam('password', ''); // 패스워드는 선택적
    $device_type = loadParam('device_type', 'unknown'); // 디바이스 타입 추가
    
    // 사용자 조회
    $user = $GLOBALS['gosapi']->get_gos_user($login_id);
    
    if (!$user) {
        // 사용자가 없으면 자동 회원가입 진행
        $GLOBALS['gosapi']->log_gos_activity(null, 'register_attempt', true, "Auto registration attempt for: $login_id");
        
        // 이메일 형식인지 사용자명인지 판단
        $is_email = filter_var($login_id, FILTER_VALIDATE_EMAIL);
        
        if ($is_email) {
            // 이메일로 입력한 경우
            $email = $login_id;
            $username = explode('@', $email)[0]; // 이메일에서 사용자명 추출
        } else {
            // 사용자명으로 입력한 경우
            $username = $login_id;
            $email = $login_id . '@gos.auto'; // 자동 이메일 생성
        }
        
        // 중복 체크 (사용자명 또는 이메일이 이미 존재하는지)
        $duplicate_check_sql = "SELECT id FROM GOS_users 
                               WHERE (username = ? OR email = ?) 
                               AND deleted_at IS NULL";
        $duplicate_user = $GLOBALS['gosapi']->query_fetch_object($duplicate_check_sql, [$username, $email]);
        
        if ($duplicate_user) {
            $GLOBALS['gosapi']->log_gos_activity(null, 'register_failed', false, 'Username or email already exists');
            $GLOBALS['gosapi']->error('Username or email already exists', 409);
        }
        
        // 새 사용자 생성
        $insert_sql = "INSERT INTO GOS_users (
                          username, email, role, first_name, last_name, 
                          status, language_preference, created_at
                       ) VALUES (?, ?, 'student', ?, ?, 'active', 'ko', NOW())";
        
        // 이름 생성 (사용자명 기반)
        $first_name = 'User';
        $last_name = $username;
        
        $new_user_id = $GLOBALS['gosapi']->insert($insert_sql, [
            $username, 
            $email, 
            $first_name, 
            $last_name
        ]);
        
        if (!$new_user_id) {
            $GLOBALS['gosapi']->log_gos_activity(null, 'register_failed', false, 'Failed to create user account');
            $GLOBALS['gosapi']->error('Failed to create user account', 500);
        }
        
        // 새로 생성된 사용자 정보 조회
        $user = $GLOBALS['gosapi']->query_fetch_object(
            "SELECT id, username, email, role, status, first_name, last_name, 
                    nickname, profile_image, language_preference, login_count
             FROM GOS_users WHERE id = ?", 
            [$new_user_id]
        );
        
        // 회원가입 성공 로그
        $GLOBALS['gosapi']->log_gos_activity($new_user_id, 'register', true, "Auto registration successful");
        
        $is_new_user = true;
    } else {
        $is_new_user = false;
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
    $action_type = $is_new_user ? 'first_login' : 'login';
    $GLOBALS['gosapi']->log_gos_activity($user->id, $action_type, true);
    
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
            'login_count' => $user->login_count + 1,
            'is_new_user' => $is_new_user
        ]
    ];
    
    $success_message = $is_new_user ? 'Account created and login successful' : 'Login successful';
    $GLOBALS['gosapi']->success($response_data, $success_message);
    
} catch (Exception $e) {
    error_log('Login/Register API Error: ' . $e->getMessage());
    $GLOBALS['gosapi']->error('Internal server error', 500);
}
?>