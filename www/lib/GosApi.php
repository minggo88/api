<?php
// =====================================================
// api/www/v1.0/auth/login/index.php
// 실제 DB 연결 버전
// =====================================================

// 에러 표시 활성화 (개발용)
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // GosApi 포함
    include dirname(__file__) . "/../../../lib/GosApi.php";
    
    // 파라미터 받기
    $login_id = checkEmpty(loadParam('login_id'), 'login_id');
    $device_type = setDefault(loadParam('device_type'), 'mobile');
    
    // 실제 DB에서 사용자 찾기
    $user = $GLOBALS['gosapi']->get_gos_user($login_id);
    
    if(!$user) {
        // 사용자 없으면 더미 데이터 반환 (테스트용)
        $GLOBALS['gosapi']->success([
            'user' => [
                'id' => 999,
                'username' => $login_id,
                'email' => $login_id . '@test.com',
                'role' => 'student',
                'first_name' => 'Test',
                'last_name' => 'User',
                'status' => 'active'
            ],
            'note' => 'User not found in DB, returning test data'
        ], 'Login successful (test mode)');
    } else {
        // 실제 사용자 데이터 반환
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
        ], 'Login successful (real data)');
    }
    
} catch (Exception $e) {
    // 에러 발생 시 상세 정보 표시
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'error_type' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'post_data' => $_POST,
        'get_data' => $_GET
    ]);
}

?>