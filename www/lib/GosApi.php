<?php
// =====================================================
// lib/GosApi.php
// GOS 전용 API 클래스 (SimpleRestful 상속)
// =====================================================

include dirname(__file__) . '/SimpleRestful.php';
include dirname(__file__) . '/Coind.php';
include dirname(__file__) . '/vendor/autoload.php';

if (!defined('__LOADED_GOSAPI__')) {
    class GosApi extends SimpleRestful {
        
        public $default_exchange = 'KRW';
        
        /**
         * GOS API Class 생성자
         * TradeApi와 동일한 구조로 SimpleRestful 상속
         */
        public function __construct() {
            $this->set_cache_dir(dirname(__file__) . '/../cache/');
            $this->set_logging(false);
            $this->set_log_dir(dirname(__file__) . "/../log/");
            parent::__construct(); // SimpleRestful의 DB 연결 등 초기화
            $this->_set_auth_env();
            $this->set_default_exchange();
        }
        
        // ===== TradeApi와 동일한 환경 설정 =====
        
        private function _set_auth_env() {
            // TradeApi와 동일한 세션 설정 (필요시)
        }
        
        public function set_default_exchange() {
            // 기본 거래소 설정 (필요시 확장)
            $this->default_exchange = 'KRW';
        }
        
        // ===== 기존 TradeApi 호환 함수들 =====
        
        /**
         * 전화번호 정리 (TradeApi에서 가져옴)
         */
        public function reset_phone_number($phone) {
            $phone = preg_replace('/[^0-9]/', '', $phone);
            if (substr($phone, 0, 2) == '82') {
                $phone = '0' . substr($phone, 2);
            }
            return $phone;
        }
        
        /**
         * 미디어 타입 체크 (TradeApi에서 가져옴)
         */
        public function checkMedia($media) {
            $allowed = ['email', 'phone', 'mobile'];
            if (!in_array($media, $allowed)) {
                $this->error('011', 'Invalid media type');
            }
            return $media;
        }
        
        /**
         * 숫자 문자열 변환
         */
        public function numtostr($number) {
            if (is_numeric($number)) {
                return rtrim(rtrim(sprintf('%.10f', $number), '0'), '.');
            }
            return $number;
        }
        
        /**
         * 이메일 유효성 검사
         */
        public function validateEmail($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        }
        
        /**
         * 안전한 파일명 생성
         */
        public function generateSafeFilename($original_name) {
            $extension = pathinfo($original_name, PATHINFO_EXTENSION);
            $filename = date('YmdHis') . '_' . uniqid() . '.' . $extension;
            return $filename;
        }
        
        // ===== GOS 전용 추가 함수들 =====
        
        /**
         * GOS 회원 가입 여부 확인
         */
        public function check_gos_join($media, $values = array()) {
            if (empty($values)) return array();
            
            $field = ($media == 'email') ? 'email' : 'phone';
            $placeholders = str_repeat('?,', count($values) - 1) . '?';
            
            $sql = "SELECT {$field} as values, 
                           CASE 
                               WHEN status = 'active' THEN 'joined'
                               WHEN status = 'pending' THEN 'pending'
                               WHEN status = 'inactive' THEN 'inactive'
                               ELSE 'unknown'
                           END as status
                    FROM GOS_users 
                    WHERE {$field} IN ({$placeholders}) 
                    AND deleted_at IS NULL";
            
            return $this->query_list_object($sql, $values);
        }
        
        /**
         * GOS 사용자 정보 조회
         */
        public function get_gos_user($login_id) {
            $sql = "SELECT id, username, email, role, status, first_name, last_name 
                    FROM GOS_users 
                    WHERE (email = ? OR username = ?) 
                    AND deleted_at IS NULL";
            
            return $this->query_fetch_object($sql, [$login_id, $login_id]);
        }
        
        /**
         * GOS 사용자 생성
         */
        public function create_gos_user($data) {
            $sql = "INSERT INTO GOS_users (
                        username, email, role, first_name, last_name, phone, 
                        status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())";
            
            $params = [
                $data['username'], 
                $data['email'], 
                $data['role'] ?? 'student', 
                $data['first_name'] ?? '', 
                $data['last_name'] ?? '', 
                $data['phone'] ?? ''
            ];
            
            return $this->insert($sql, $params);
        }
        
        /**
         * GOS 활동 로그 저장
         */
        public function log_gos_activity($user_id, $action_type, $success = true, $error_message = null) {
            $sql = "INSERT INTO GOS_user_logs (
                        user_id, action_type, ip_address, success, error_message, created_at
                    ) VALUES (?, ?, ?, ?, ?, NOW())";
            
            return $this->insert($sql, [
                $user_id, 
                $action_type, 
                $_SERVER['REMOTE_ADDR'] ?? '', 
                $success, 
                $error_message
            ]);
        }용자 세션 생성
         */
        public function create_gos_session($user_id, $device_type = 'mobile') {
            $session_token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            // 기존 세션 비활성화
            $this->query("UPDATE GOS_user_sessions SET is_active = FALSE WHERE user_id = ?", [$user_id]);
            
            // 새 세션 생성
            $device_info = json_encode([
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            
            $session_id = $this->insert("
                INSERT INTO GOS_user_sessions (
                    user_id, session_token, device_type, device_info, ip_address, 
                    user_agent, expires_at, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())", 
                [$user_id, $session_token, $device_type, $device_info, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], $expires_at]
            );
            
            return $session_token;
        }
        
        /**
         * GOS 세션 확인
         */
        public function verify_gos_session($token) {
            $sql = "SELECT s.*, u.id as user_id, u.username, u.email, u.role, u.status, u.first_name, u.last_name
                    FROM GOS_user_sessions s
                    JOIN GOS_users u ON s.user_id = u.id
                    WHERE s.session_token = ? 
                    AND s.is_active = TRUE 
                    AND s.expires_at > NOW()
                    AND u.status = 'active'
                    AND u.deleted_at IS NULL";
            
            return $this->query_fetch_object($sql, [$token]);
        }
        
        /**
         * GOS 사용자 정보 조회
         */
        public function get_gos_user($login_id) {
            $sql = "SELECT id, username, email, role, status, first_name, last_name 
                    FROM GOS_users 
                    WHERE (email = ? OR username = ?) 
                    AND deleted_at IS NULL";
            
            return $this->query_fetch_object($sql, [$login_id, $login_id]);
        }
        
        /**
         * GOS 사용자 생성
         */
        public function create_gos_user($data) {
            $sql = "INSERT INTO GOS_users (
                        username, email, role, first_name, last_name, phone, 
                        status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())";
            
            $params = [
                $data['username'], 
                $data['email'], 
                $data['role'] ?? 'student', 
                $data['first_name'] ?? '', 
                $data['last_name'] ?? '', 
                $data['phone'] ?? ''
            ];
            
            return $this->insert($sql, $params);
        }
        
        /**
         * GOS 활동 로그 저장
         */
        public function log_gos_activity($user_id, $action_type, $success = true, $error_message = null) {
            $sql = "INSERT INTO GOS_user_logs (
                        user_id, action_type, ip_address, success, error_message, created_at
                    ) VALUES (?, ?, ?, ?, ?, NOW())";
            
            return $this->insert($sql, [
                $user_id, 
                $action_type, 
                $_SERVER['REMOTE_ADDR'] ?? '', 
                $success, 
                $error_message
            ]);
        }
    }
    
    // 전역 변수로 인스턴스 생성 (TradeApi 방식과 동일)
    $GLOBALS['gosapi'] = new GosApi();
    define('__LOADED_GOSAPI__', true);
    
    // ===== TradeApi 호환 전역 함수들 =====
    
    if (!function_exists('loadParam')) {
        function loadParam($key, $default = null) {
            return $GLOBALS['gosapi']->loadParam($key, $default);
        }
    }
    
    if (!function_exists('checkEmpty')) {
        function checkEmpty($value, $field_name) {
            return $GLOBALS['gosapi']->checkEmpty($value, $field_name);
        }
    }
    
    if (!function_exists('setDefault')) {
        function setDefault($value, $default) {
            return $GLOBALS['gosapi']->setDefault($value, $default);
        }
    }
    
    if (!function_exists('checkMedia')) {
        function checkMedia($media) {
            return $GLOBALS['gosapi']->checkMedia($media);
        }
    }
    
    if (!function_exists('__')) {
        function __($text) {
            return $GLOBALS['gosapi']->__($text);
        }
    }
}

?>