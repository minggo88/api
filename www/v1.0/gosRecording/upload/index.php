<?php
// =====================================================
// /home/ubuntu/www/api/www/v1.0/recording/upload/index.php
// =====================================================

// CORS 헤더 설정
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// OPTIONS 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// 에러 로깅 활성화
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/php_errors.log');

try {
    // GosApi 포함 - 경로 확인
    $gosapi_path = __DIR__ . '/../../../../lib/GosApi.php';
    if (!file_exists($gosapi_path)) {
        throw new Exception("GosApi.php not found at: $gosapi_path");
    }
    require_once $gosapi_path;
    
    // POST 요청만 허용
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => 'Only POST method allowed'
        ]);
        exit(0);
    }
    
    // 디버깅 정보 로그
    error_log('Recording upload started');
    error_log('POST data: ' . print_r($_POST, true));
    error_log('FILES data: ' . print_r($_FILES, true));
    
    // 파라미터 유효성 검사
    if (!isset($_POST['user_id']) || empty($_POST['user_id'])) {
        throw new Exception('user_id is required');
    }
    if (!isset($_POST['page_id']) || empty($_POST['page_id'])) {
        throw new Exception('page_id is required');
    }
    
    $user_id = intval($_POST['user_id']);
    $page_id = intval($_POST['page_id']);
    $slide_number = isset($_POST['slide_number']) ? intval($_POST['slide_number']) : 1;
    $duration_seconds = isset($_POST['duration_seconds']) ? intval($_POST['duration_seconds']) : 0;
    $recording_format = isset($_POST['recording_format']) ? $_POST['recording_format'] : 'mp3';
    $recording_quality = isset($_POST['recording_quality']) ? $_POST['recording_quality'] : 'standard';
    $device_info = isset($_POST['device_info']) ? $_POST['device_info'] : '';
    
    // 파일 업로드 확인
    if (!isset($_FILES['recording_file'])) {
        throw new Exception('No recording file in request');
    }
    
    $uploaded_file = $_FILES['recording_file'];
    if ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        $error_msg = isset($error_messages[$uploaded_file['error']]) 
            ? $error_messages[$uploaded_file['error']] 
            : 'Unknown upload error';
        throw new Exception("File upload failed: $error_msg (Code: {$uploaded_file['error']})");
    }
    
    $file_size = $uploaded_file['size'];
    $temp_path = $uploaded_file['tmp_name'];
    
    // 파일 크기 제한 (50MB)
    if ($file_size > 50 * 1024 * 1024) {
        throw new Exception('File size exceeds 50MB limit');
    }
    
    // 임시 파일 존재 확인
    if (!file_exists($temp_path)) {
        throw new Exception('Temporary file not found');
    }
    
    // 업로드 디렉토리 생성
    $upload_base_dir = '/home/ubuntu/www/uploads/recordings';
    $upload_dir = $upload_base_dir . '/' . date('Y/m/d');
    
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception('Failed to create upload directory: ' . $upload_dir);
        }
    }
    
    // 디렉토리 권한 확인
    if (!is_writable($upload_dir)) {
        throw new Exception('Upload directory is not writable: ' . $upload_dir);
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
        throw new Exception('Failed to save uploaded file to: ' . $file_path);
    }
    
    error_log('File saved successfully: ' . $file_path);
    
    // GosApi 사용 가능한지 확인
    if (!isset($GLOBALS['gosapi']) || !$GLOBALS['gosapi']) {
        // GosApi가 없는 경우 직접 DB 연결 (임시)
        error_log('GosApi not available, using direct response');
        
        $response_data = [
            'success' => true,
            'message' => 'Recording uploaded successfully (without DB)',
            'recording' => [
                'id' => time(), // 임시 ID
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
        
        echo json_encode($response_data);
        exit(0);
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
        throw new Exception('Failed to save recording information to database');
    }
    
    $recording_id = $GLOBALS['gosapi']->get_last_insert_id();
    
    // 활동 로그
    $GLOBALS['gosapi']->log_gos_activity($user_id, 'recording_upload', true, 
        "Recording uploaded: page_id={$page_id}, slide={$slide_number}, size={$file_size}bytes");
    
    // 응답 데이터
    $response_data = [
        'success' => true,
        'message' => 'Recording uploaded successfully',
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
    
    echo json_encode($response_data);
    
} catch (Exception $e) {
    error_log('Recording Upload Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    // 에러 발생 시 임시 파일 정리
    if (isset($file_path) && file_exists($file_path)) {
        unlink($file_path);
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Upload failed: ' . $e->getMessage(),
        'debug_info' => [
            'php_version' => phpversion(),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit')
        ]
    ]);
}
?>