<?php
// =====================================================
// api/www/v1.0/auth/checkjoin/index.php
// 회원 가입 여부 확인 (GosApi 버전)
// =====================================================

// CORS 헤더 설정

// OPTIONS 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// GosApi 포함
require_once __DIR__ . '/../../../lib/GosApi.php';

try {
    // 파라미터 검증
    $media = checkEmpty(loadParam('media'), 'media'); // email, phone, username
    $ids = setDefault(loadParam('ids'), ''); // 이메일, 전화번호 또는 사용자명

    // 미디어 타입 유효성 검사
    $allowed_media = ['email', 'phone', 'username'];
    $media = strtolower($media);
    if (!in_array($media, $allowed_media)) {
        $GLOBALS['gosapi']->error("Invalid media type. Allowed: " . implode(', ', $allowed_media));
    }

    if (empty($ids)) {
        $GLOBALS['gosapi']->error('No IDs provided');
    }

    // 콤마로 구분된 값들을 배열로 변환
    $values = explode(',', $ids);
    $origin_values = $values;

    // 전화번호인 경우 정규화
    if ($media == 'phone') {
        for ($i = 0; $i < count($values); $i++) {
            $values[$i] = preg_replace('/[^0-9]/', '', $values[$i]);
            // 한국 전화번호 정규화 (필요시)
            $values[$i] = normalize_phone_number($values[$i]);
        }
    }

    // 이메일인 경우 정규화
    if ($media == 'email') {
        for ($i = 0; $i < count($values); $i++) {
            $values[$i] = strtolower(trim($values[$i]));
        }
    }

    // 사용자명인 경우 정규화
    if ($media == 'username') {
        for ($i = 0; $i < count($values); $i++) {
            $values[$i] = trim($values[$i]);
        }
    }

    // 기존 회원 확인
    $joined = check_gos_join($media, $values);
    
    $result_map = array();
    foreach ($joined as $row) {
        if ($row->values && !isset($result_map[$row->values])) {
            $i = array_search($row->values, $values);
            if ($i !== false) {
                $result_map[$origin_values[$i]] = $row->status;
            }
        }
    }

    // 결과 배열 구성
    $response_data = array();
    foreach ($origin_values as $original_id) {
        $status = isset($result_map[$original_id]) ? $result_map[$original_id] : 'available';
        $response_data[] = array(
            'id' => $original_id,
            'status' => __($status),
            'available' => ($status === 'available')
        );
    }

    $GLOBALS['gosapi']->success($response_data);

} catch (Exception $e) {
    error_log('CheckJoin API Error: ' . $e->getMessage());
    $GLOBALS['gosapi']->error('Internal server error', 500);
}

/**
 * GOS 회원 가입 상태 확인
 */
function check_gos_join($media, $values) {
    global $GLOBALS;
    
    if (empty($values)) return array();
    
    // 필드명 결정
    $field_map = [
        'email' => 'email',
        'phone' => 'phone',
        'username' => 'username'
    ];
    
    $field = $field_map[$media];
    $placeholders = str_repeat('?,', count($values) - 1) . '?';
    
    $sql = "SELECT {$field} as values, 
                   CASE 
                       WHEN status = 'active' THEN 'joined'
                       WHEN status = 'pending' THEN 'pending'
                       WHEN status = 'inactive' THEN 'inactive'
                       WHEN status = 'suspended' THEN 'suspended'
                       ELSE 'unknown'
                   END as status
            FROM GOS_users 
            WHERE {$field} IN ({$placeholders}) 
            AND deleted_at IS NULL";
    
    return $GLOBALS['gosapi']->query_fetch_all($sql, $values);
}

/**
 * 전화번호 정규화 (한국 형식)
 */
function normalize_phone_number($phone) {
    // 숫자만 추출
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // 한국 전화번호 정규화
    if (strlen($phone) == 11 && substr($phone, 0, 3) == '010') {
        return $phone; // 이미 정규화됨
    } elseif (strlen($phone) == 10 && substr($phone, 0, 2) == '01') {
        return '0' . $phone; // 0 추가
    } elseif (strlen($phone) == 9 && substr($phone, 0, 1) == '1') {
        return '01' . $phone; // 01 추가
    }
    
    return $phone; // 그대로 반환
}

?>