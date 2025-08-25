<?php
// =====================================================
// /home/ubuntu/www/api/www/v1.0/gosRecording/send/index.php
// =====================================================

// OPTIONS 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// 에러 로깅 활성화
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/php_errors.log');

// GosApi 포함 - 올바른 경로 수정
require_once __DIR__ . '/../../../lib/GosApi.php';

try {
    // POST 요청만 허용
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $GLOBALS['gosapi']->error('Only POST method allowed', 405);
    }
    
    // 디버깅 정보 로그
    error_log('Recording send started');
    error_log('POST data: ' . print_r($_POST, true));
    
    // 파라미터 받기
    $recording_id = checkEmpty(loadParam('recording_id'), 'recording_id');
    $user_id = checkEmpty(loadParam('user_id'), 'user_id');
    
    // 녹음 정보 확인 (함수명 수정: get_single_row -> query_fetch_object)
    $recording_sql = "SELECT * FROM GOS_student_recordings 
                      WHERE id = ? AND user_id = ? AND upload_status = 'completed'";
    $recording = $GLOBALS['gosapi']->query_fetch_object($recording_sql, [$recording_id, $user_id]);
    
    if (!$recording) {
        error_log("Recording not found: id={$recording_id}, user_id={$user_id}");
        $GLOBALS['gosapi']->error('Recording not found or not completed', 404);
    }
    
    // 이미 전송되었는지 확인
    if ($recording->is_sent_to_teacher == 1) {
        error_log("Recording already sent: id={$recording_id}");
        $GLOBALS['gosapi']->error('Recording already sent to teacher', 400);
    }
    
    // 선생님에게 전송 상태로 업데이트
    $update_sql = "UPDATE GOS_student_recordings 
                   SET is_sent_to_teacher = 1, sent_at = NOW(), updated_at = NOW() 
                   WHERE id = ?";
    
    $result = $GLOBALS['gosapi']->query($update_sql, [$recording_id]);
    
    if (!$result) {
        error_log("Failed to update recording status: id={$recording_id}");
        $GLOBALS['gosapi']->error('Failed to update recording status', 500);
    }
    
    error_log("Recording sent successfully: id={$recording_id}");
    
    // 활동 로그
    $GLOBALS['gosapi']->log_gos_activity($user_id, 'recording_send', true, 
        "Recording sent to teacher: recording_id={$recording_id}");
    
    // 응답 데이터
    $response_data = [
        'recording' => [
            'id' => $recording->id,
            'user_id' => $recording->user_id,
            'page_id' => $recording->page_id,
            'slide_number' => $recording->slide_number,
            'filename' => $recording->recording_filename,
            'url' => $recording->recording_url,
            'is_sent_to_teacher' => 1,
            'sent_at' => date('Y-m-d H:i:s')
        ]
    ];
    
    $GLOBALS['gosapi']->success($response_data, 'Recording sent to teacher successfully');
    
} catch (Exception $e) {
    error_log('Recording Send API Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    $GLOBALS['gosapi']->error('Internal server error: ' . $e->getMessage(), 500);
}
?>