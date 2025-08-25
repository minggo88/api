<?php
// =====================================================
// /home/ubuntu/www/api/www/v1.0/gosRecording/upload/index.php
// =====================================================

// OPTIONS 요청 처리 (서버 레벨 CORS 설정 사용)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// GosApi 포함
require_once __DIR__ . '/../../../lib/GosApi.php';

try {
    // POST 요청만 허용
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $GLOBALS['gosapi']->error('Only POST method allowed', 405);
    }
    
    // 파라미터 받기
    $user_id = checkEmpty(loadParam('user_id'), 'user_id');
    $page_id = checkEmpty(loadParam('page_id'), 'page_id');
    $slide_number = loadParam('slide_number', 1);
    $duration_seconds = loadParam('duration_seconds', 0);
    $recording_format = loadParam('recording_format', 'mp3');
    $recording_quality = loadParam('recording_quality', 'standard');
    $device_info = loadParam('device_info', '');
    
    // 파일 업로드 확인
    if (!isset($_FILES['recording_file']) || $_FILES['recording_file']['error'] !== UPLOAD_ERR_OK) {
        $GLOBALS['gosapi']->error('No recording file uploaded or upload failed', 400);
    }
    
    $uploaded_file = $_FILES['recording_file'];
    $file_size = $uploaded_file['size'];
    $temp_path = $uploaded_file['tmp_name'];
    
    // 파일 크기 제한 (50MB)
    if ($file_size > 50 * 1024 * 1024) {
        $GLOBALS['gosapi']->error('File size exceeds 50MB limit', 400);
    }
    
    // 업로드 디렉토리 생성
    $upload_base_dir = '/home/ubuntu/www/uploads/recordings';
    $upload_dir = $upload_base_dir . '/' . date('Y/m/d');
    
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            $GLOBALS['gosapi']->error('Failed to create upload directory', 500);
        }
    }
    
    // 파일명 생성 (중복 방지)
    $file_extension = pathinfo($uploaded_file['name'], PATHINFO_EXTENSION);
    if (empty($file_extension)) {
        $file_extension = $recording_format;
    }
    
    $filename = 'recording_' . $user_id . '_' . $page_id . '_' . $slide_number . '_' . time() . '_' . uniqid() . '.' . $file_extension;
    $file_path = $upload_dir . '/' . $filename;
    $relative_path = 'recordings/' . date('Y/m/d') . '/' . $filename;
    $file_url = 'https://api.dev.assettea.com/uploads/' . $relative_path;
    
    // 파일 이동
    if (!move_uploaded_file($temp_path, $file_path)) {
        $GLOBALS['gosapi']->error('Failed to save uploaded file', 500);
    }
    
    // DB에 녹음 정보 저장
    $insert_sql = "INSERT INTO GOS_student_recordings 
                   (user_id, page_id, slide_number, recording_filename, recording_path, 
                    recording_url, file_size, duration_seconds, recording_format, 
                    recording_quality, device_info, upload_status, uploaded_at) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW())";
    
    $params = [
        $user_id, 
        $page_id, 
        $slide_number, 
        $filename, 
        $relative_path,
        $file_url,
        $file_size,
        $duration_seconds,
        $recording_format,
        $recording_quality,
        $device_info
    ];
    
    $result = $GLOBALS['gosapi']->query($insert_sql, $params);
    
    if (!$result) {
        // DB 저장 실패 시 업로드된 파일 삭제
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        $GLOBALS['gosapi']->error('Failed to save recording information to database', 500);
    }
    
    $recording_id = $GLOBALS['gosapi']->get_last_insert_id();
    
    // 활동 로그
    $GLOBALS['gosapi']->log_gos_activity($user_id, 'recording_upload', true, 
        "Recording uploaded: page_id={$page_id}, slide={$slide_number}, size={$file_size}bytes");
    
    // 응답 데이터
    $response_data = [
        'recording' => [
            'id' => $recording_id,
            'user_id' => $user_id,
            'page_id' => $page_id,
            'slide_number' => $slide_number,
            'filename' => $filename,
            'url' => $file_url,
            'file_size' => $file_size,
            'duration_seconds' => $duration_seconds,
            'format' => $recording_format,
            'quality' => $recording_quality,
            'upload_status' => 'completed',
            'uploaded_at' => date('Y-m-d H:i:s')
        ]
    ];
    
    $GLOBALS['gosapi']->success($response_data, 'Recording uploaded successfully');
    
} catch (Exception $e) {
    error_log('Recording Upload API Error: ' . $e->getMessage());
    
    // 에러 발생 시 임시 파일 정리
    if (isset($file_path) && file_exists($file_path)) {
        unlink($file_path);
    }
    
    $GLOBALS['gosapi']->error('Internal server error', 500);
}
?>