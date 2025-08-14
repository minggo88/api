<?php
// =====================================================
// /home/ubuntu/www/api/www/lib/GosApi.php
// 서버용 GosApi - 중복 include 제거
// =====================================================

if (!defined('__LOADED_GOSAPI__')) {
    class GosApi {
        private $db_connection = null;
        
        public function __construct() {
            // DB 연결은 필요할 때만 수행
        }
        
        // ===== 실제 DB 설정 사용 =====
        private function connectDatabase() {
            if ($this->db_connection !== null) {
                return $this->db_connection;
            }
            
            try {
                $this->db_connection = new PDO(
                    "mysql:host=kkikda-dev.catyypkt8dey.ap-northeast-2.rds.amazonaws.com;dbname=kkikda;charset=utf8mb4",
                    'kkikda',
                    'KKe8IuK28Due82A'
                );
                $this->db_connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch(PDOException $e) {
                error_log('GosApi DB Connection Failed: ' . $e->getMessage());
                $this->db_connection = null;
            }
            
            return $this->db_connection;
        }
        
        // ===== TradeApi 호환 함수들 =====
        
        public function loadParam($key, $default = null) {
            if (isset($_POST[$key])) {
                return $_POST[$key];
            } elseif (isset($_GET[$key])) {
                return $_GET[$key];
            }
            return $default;
        }
        
        public function checkEmpty($value, $field_name) {
            if (empty($value)) {
                $this->error("Required field '{$field_name}' is missing or empty");
            }
            return $value;
        }
        
        public function setDefault($value, $default) {
            return empty($value) ? $default : $value;
        }
        
        public function escape($value) {
            if ($this->connectDatabase()) {
                return substr($this->db_connection->quote($value), 1, -1);
            }
            return addslashes($value);
        }
        
        // ===== 데이터베이스 함수들 =====
        
        public function query_fetch_all($sql, $params = []) {
            if (!$this->connectDatabase()) {
                $this->error('Database connection failed');
            }
            
            try {
                $stmt = $this->db_connection->prepare($sql);
                $stmt->execute($params);
                return $stmt->fetchAll(PDO::FETCH_OBJ);
            } catch(PDOException $e) {
                $this->error('Query failed: ' . $e->getMessage());
            }
        }
        
        public function query_fetch_object($sql, $params = []) {
            if (!$this->connectDatabase()) {
                $this->error('Database connection failed');
            }
            
            try {
                $stmt = $this->db_connection->prepare($sql);
                $stmt->execute($params);
                return $stmt->fetch(PDO::FETCH_OBJ);
            } catch(PDOException $e) {
                $this->error('Query failed: ' . $e->getMessage());
            }
        }
        
        public function query($sql, $params = []) {
            if (!$this->connectDatabase()) {
                $this->error('Database connection failed');
            }
            
            try {
                $stmt = $this->db_connection->prepare($sql);
                $stmt->execute($params);
                return $stmt->rowCount();
            } catch(PDOException $e) {
                $this->error('Query failed: ' . $e->getMessage());
            }
        }
        
        public function insert($sql, $params = []) {
            if (!$this->connectDatabase()) {
                $this->error('Database connection failed');
            }
            
            try {
                $stmt = $this->db_connection->prepare($sql);
                $stmt->execute($params);
                return $this->db_connection->lastInsertId();
            } catch(PDOException $e) {
                $this->error('Insert failed: ' . $e->getMessage());
            }
        }
        
        // ===== 응답 함수들 =====
        
        public function success($data = null, $message = 'Success') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => $message,
                'data' => $data,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            exit;
        }
        
        public function error($message, $code = 400) {
            header('Content-Type: application/json');
            http_response_code($code);
            echo json_encode([
                'success' => false,
                'error' => $message,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            exit;
        }
        
        // ===== GOS 전용 함수들 =====
        
        public function get_gos_user($login_id) {
            $sql = "SELECT id, username, email, role, status, first_name, last_name 
                    FROM GOS_users 
                    WHERE (email = ? OR username = ?) 
                    AND deleted_at IS NULL";
            
            return $this->query_fetch_object($sql, [$login_id, $login_id]);
        }
        
        public function log_gos_activity($user_id, $action_type, $success = true, $error_message = null) {
            if (!$this->connectDatabase()) {
                return false;
            }
            
            $sql = "INSERT INTO GOS_user_logs (
                        user_id, action_type, ip_address, user_agent, success, error_message, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW())";
            
            try {
                return $this->insert($sql, [
                    $user_id, 
                    $action_type, 
                    $_SERVER['REMOTE_ADDR'] ?? '', 
                    $_SERVER['HTTP_USER_AGENT'] ?? '',
                    $success, 
                    $error_message
                ]);
            } catch (Exception $e) {
                return false;
            }
        }
        
        // ===== 다국어 지원 =====
        public function __($text) {
            return $text;
        }
    }
    
    // 전역 변수로 인스턴스 생성
    $GLOBALS['gosapi'] = new GosApi();
    define('__LOADED_GOSAPI__', true);
    
    // ===== 전역 함수들 =====
    
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
    
    if (!function_exists('__')) {
        function __($text) {
            return $GLOBALS['gosapi']->__($text);
        }
    }
}

?>