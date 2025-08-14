<?php
// =====================================================
// api/www/v1.0/auth/register/index.php
// 회원가입 (GosApi 버전, 패스워드 없음)
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
    // 파라미터 받기 및 검증
    $username = checkEmpty(loadParam('username'), 'username');
    $email = checkEmpty(loadParam('email'), 'email');
    $first_name = setDefault(loadParam('first_name'), '');
    $last_name = setDefault(loadParam('last_name'), '');
    $phone = setDefault(loadParam('phone'), '');
    $role = setDefault(loadParam('role'), 'student'); // student, teacher, admin
    $language_preference = setDefault(loadParam('language_preference'), 'ko');
    
    // 자동 생성 모드 (login_id만 제공된 경우)
    $login_id = trim(loadParam('login_id', ''));
    
    if (!empty($login_id) && empty($username) && empty($email)) {
        // login_id로 자동 생성
        if (filter_var($login_id, FILTER_VALIDATE_EMAIL)) {
            // 이메일 형식인 경우
            $email = $login_id;
            $username = explode('@', $email)[0];
        } else {
            // 사용자명 형식인 경우
            $username = $login_id;
            $email = $login_id . '@gos.auto';
        }
        $last_name = $username;
    }

    // 이메일 형식 검증
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $GLOBALS['gosapi']->error(__('Invalid email format'));
    }

    // 사용자명 유효성 검사
    if (strlen($username) < 2 || strlen($username) > 50) {
        $GLOBALS['gosapi']->error(__('Username must be between 2 and 50 characters'));
    }

    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
        $GLOBALS['gosapi']->error(__('Username can only contain letters, numbers, underscore and dash'));
    }

    // 권한 검사
    $allowed_roles = ['student', 'teacher', 'admin'];
    if (!in_array($role, $allowed_roles)) {
        $role = 'student';
    }

    // 전화번호 정규화 (있는 경우)
    if (!empty($phone)) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) > 0 && strlen($phone) < 10) {
            $GLOBALS['gosapi']->error(__('Invalid phone number format'));
        }
    }

    // 중복 확인
    $existing_sql = "SELECT id, username, email FROM GOS_users 
                     WHERE (email = ? OR username = ?) 
                     AND deleted_at IS NULL";
    $existing = $GLOBALS['gosapi']->query_fetch_object($existing_sql, [$email, $username]);
    
    if ($existing) {
        if ($existing->email === $email) {
            $GLOBALS['gosapi']->error(__('Email already exists'), 409);
        }
        if ($existing->username === $username) {
            $GLOBALS['gosapi']->error(__('Username already exists'), 409);
        }
    }

    // 전화번호 중복 확인 (있는 경우)
    if (!empty($phone)) {
        $phone_check = $GLOBALS['gosapi']->query_fetch_object(
            "SELECT id FROM GOS_users WHERE phone = ? AND deleted_at IS NULL", 
            [$phone]
        );
        if ($phone_check) {
            $GLOBALS['gosapi']->error(__('Phone number already exists'), 409);
        }
    }

    // 사용자 생성
    $insert_sql = "INSERT INTO GOS_users (
                      username, email, role, first_name, last_name, phone, 
                      status, language_preference, created_at
                   ) VALUES (?, ?, ?, ?, ?, ?, 'active', ?, NOW())";

    $params = [$username, $email, $role, $first_name, $last_name, $phone, $language_preference];
    $user_id = $GLOBALS['gosapi']->insert($insert_sql, $params);

    if ($user_id) {
        // 회원가입 로그
        $GLOBALS['gosapi']->log_gos_activity($user_id, 'register', true, 'Registration successful');
        
        // 생성된 사용자 정보 조회
        $user_sql = "SELECT id, username, email, role, status, first_name, last_name, 
                            phone, nickname, profile_image, language_preference, login_count
                     FROM GOS_users WHERE id = ?";
        $user = $GLOBALS['gosapi']->query_fetch_object($user_sql, [$user_id]);
        
        // 성공 응답
        $response_data = [
            'user' => [
                'id' => (int)$user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'phone' => $user->phone,
                'nickname' => $user->nickname,
                'profile_image' => $user->profile_image,
                'language_preference' => $user->language_preference,
                'login_count' => (int)$user->login_count,
                'status' => $user->status
            ]
        ];
        
        $GLOBALS['gosapi']->success($response_data, __('Registration successful'));
    } else {
        $GLOBALS['gosapi']->error(__('Registration failed'), 500);
    }

} catch (Exception $e) {
    error_log('Register API Error: ' . $e->getMessage());
    
    // 실패 로그 (사용자 ID가 있는 경우에만)
    if (isset($user_id) && $user_id) {
        $GLOBALS['gosapi']->log_gos_activity($user_id, 'register', false, 'Registration failed: ' . $e->getMessage());
    }
    
    $GLOBALS['gosapi']->error('Internal server error', 500);
}

?>