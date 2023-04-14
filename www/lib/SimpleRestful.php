<?php
include dirname(__file__) . '/config.php';
include_once dirname(__file__) . '/vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Intervention\Image\ImageManager;

/**
 *
 */
if (!defined('__LOADED_SIMPLERESTFUL__')) {

    define('__SRF_DIR__', dirname(__DIR__));

    class SimpleRestful
    {

        /**
         * DB 접속 정보
         */
        private $db_info = array();

        /**
         * DB 링크 - 슬레이브(기본)
         */
        private $_db_link = null;

        /**
         * DB 링크 - 마스터
         */
        private $_db_link_master = null;

        /**
         * DB 링크 - 슬레이브
         */
        private $_db_link_slave = null;

        /**
         * 마지막 쿼리 실행 정보
         */
        public $_recently_query = array();

        /**
         * session key name
         */
        private $_session_key_name = 'token'; // set PHPSESSIONID or custom name

        /**
         * session time
         */
        private $_session_time = 3600; // 1 hr.

        /**
         * Simple Restful Class 생성자
         *
         * DB 접속
         * 인증 환경 설정
         * parameter 초기화 (HTML 삭제)
         * cache 폴더 지정
         * log 폴더 지정
         */
        public function __construct()
        {
            error_reporting(E_ERROR | E_WARNING | E_PARSE); // 개발시 오류 메시지표시용.
            ini_set('display_error','on'); // 개발시 오류 메시지표시용.
            // ini_set('display_error','off'); // 라이브 서비스용.
            date_default_timezone_set('Asia/Seoul');
            ob_start();
            $this->parameter_clean(array('detail_contents'));
            $this->_set_db_info();
            $this->_db_connect();
            $this->_set_memcache_info();
            $this->_set_session();
            $this->_set_i18n();
            // prepare tr. id
            if($_REQUEST['id'] && SimpleRestful::isTrid($_REQUEST['id'])) {
                $this->set_tr_id($_REQUEST['id']);
            }
            if($this->get_tr_id()=='') {
                $this->gen_tr_id();
            }
            register_shutdown_function(array($this, 'shutdown_function'));
        }

        /**
         * 종료시 사용할 작업.
         *
         * DB 연결 종료
         */
        public function shutdown_function()
        {
            $this->_db_close();
        }

        // --------------------------------------------------------------------- //
        // i18n

        private $i18n_lang = "ko"; // default language
        private $i18n_folder = __SRF_DIR__."/i18n";
        private $i18n_domain = "API";
        private $i18n_data = array();

        public function get_i18n_lang() {
            return $this->i18n_lang;
        }

        public function get_i18n_lang_db() {
            switch($this->i18n_lang) {
                case 'ko' : return 'kr'; break;
                case 'zh' : return 'cn'; break;
                default: return $this->i18n_lang;
            }
        }

        private function _set_i18n() {
            $lang = $this->i18n_lang;
            if(isset($_SESSION['lang']) && trim($_SESSION['lang'])!='') {
                $lang = $_SESSION['lang'];
            } else if(isset($_COOKIE['lang']) && trim($_COOKIE['lang'])!='') {
                $lang = $_COOKIE['lang'];
            } else if(isset($_REQUEST['lang']) && trim($_REQUEST['lang'])!='') {
                $lang = $_REQUEST['lang'];
            }
            if($lang=='kr'){
                $lang = 'ko';
            }
            if($lang=='cn'){
                $lang = 'zh';
            }
            $lang = in_array($lang, SUPPORT_LANGUAGE) ? $lang : 'ko';

            $this->i18n_lang = $lang;

            $pofile = $this->i18n_folder."/{$lang}/LC_MESSAGES/".$this->i18n_domain.".po";
            $c = $this->get_cache($pofile);
            $c = $c ? json_decode($c) : false;
            if(file_exists($pofile)) {
                if($c->gentime >= filemtime($pofile) && $c->data) { // 캐시 생성 시간이 파일 수정시간보다 크면 캐시 사용.
                    $this->i18n_data = (array) $c->data;
                } else {
                    $con = file_get_contents($pofile);
                    $con = preg_replace('/#(.*)/', '', $con); // remove comment
                    $con = preg_replace('/^\"(Project-Id-Version|Report-Msgid-Bugs-To|POT-Creation-Date|PO-Revision-Date|Last-Translator|Language-Team|Language|MIME-Version|Content-Type|Content-Transfer-Encoding|X-Generator|Plural-Forms):(.*)\"$/m', '', $con); // remove header
                    $con = str_replace('"'."\n".'"', '', $con); // concat multiline string
                    $con = explode("\n", $con);
                    $msgid = array(); $msgstr = array();
                    foreach($con as $row) {
                        $row = trim($row);
                        if(trim($row)!='' && (strpos($row, 'msgid')!==false || strpos($row, 'msgstr')!==false)) { // 빈줄 재외, msgid, msgstr 없는것 제외
                            preg_match('/^(.*)\s"(.*)"$/', trim($row), $matches); // 키 "값" 으로 추출
                            if(trim($matches[1])!='') {
                                $key = $matches[1];
                                $val = $matches[2] ? $matches[2] : '';
                                if($key=='msgid' || $key=='msgstr') {
                                    // $$key[] = $val;
                                    array_push($$key, $val);
                                }
                            }
                        }
                    }
                    if(count($msgid)==count($msgstr)) {
                        $data = array(
                            'gentime' => time(),
                            'data' =>array()
                        );
                        for($i; $i<count($msgid); $i++) {
                            $data['data'][$msgid[$i]] = $msgstr[$i];
                        }
                        $c = $this->set_cache($pofile, json_encode($data), 31536000); // 1년 캐시
                    }
                    $this->i18n_data = (array) $data['data'];
                }
            } else {
                $this->i18n_data = array();
            }
        }

        /**
         * 번역 
         * @param String $s 번역할 문장.
         * @param Array $r 치환할 키와 값. 예: array('{user_name}'=>'홍길동')\
         * @return String 번역된 문장.
         */
        public function __( $s, $r=null ) {
            $s = $this->i18n_data[$s] ? $this->i18n_data[$s] : $s;
            if($r) {
                $search = array();
                $value = array();
                foreach($r as $k => $v) {
                    $search[] = $k;
                    $value[] = $v;
                }
                $s = str_replace($search, $value, $s);
            }
            return $s;
        }

        /**
         * 번역 후 바로 화면출력
         * @param String $s 번역할 문장.
         * @param Array $r 치환할 키와 값. 예: array('{user_name}'=>'홍길동')\
         */
        public function _e( $s, $r=null ) {
            echo $this->__( $s, $r );
        }

        public function _c( $lang ) {
            $_SESSION['lang'] = $lang;
            $_COOKIE['lang'] = $lang;
            setcookie('lang', $lang, null, '/', ini_get('session.cookie_domain'));
        }

        // --------------------------------------------------------------------- //
        // session - memcache

        public function _set_session() {
            if(session_id() == '') {
                session_name('token');
                if($this->cache_method == 'memcached' && is_object($this->cache_obj)) {
                    session_set_save_handler(
                        array($this, "_session_open"),
                        array($this, "_session_close"),
                        array($this, "_session_read"),
                        array($this, "_session_write"),
                        array($this, "_session_destroy"),
                        array($this, "_session_gc")
                    );
                } else {
                    $_session_folde = __SRF_DIR__."/../../_session";
                    if(! file_exists($_session_folde)) {
                        mkdir($_session_folde, 0777, true);
                    }
                    $_session_folde = realpath($_session_folde);
                    ini_set("session.save_path",$_session_folde);
                }
                $domain = str_replace('api.', '', $_SERVER['HTTP_HOST']); // 서비스 도메인과 맞추기위해 api. 을 삭제합니다.
				$domain = substr_count($domain, '.')<2 ? 'www.'.$domain : $domain; // 라이브 서비스에서는 www.cexsctock.com 이나 www.smcc.io 같은 도메인을 사용하기 때문임.
				// 지갑하고 로그인 세션을 연동해야 해서 지갑에서 사용하는 도메인을 이용합니다.
				if(strpos($domain, 'loc.')!==false) $domain = '.loc.kmcse.com';
				elseif(strpos($domain, 'dev.')!==false) $domain = '.dev.kmcse.com';
				elseif(strpos($domain, 'stage.')!==false) $domain = '.stage.kmcse.com';
				else $domain = '.kmcse.com';
				// if(__API_RUNMODE__=='loc') {$domain = 'loc.bds.bmstrade.io';}
				// if(__API_RUNMODE__=='dev') {$domain = 'dev.bds.bmstrade.io';}
				// var_dump('$domain:', $domain);
				// var_dump('token:', $_REQUEST['token'], $_COOKIE['token']); // 로그인 세션의 연동은 token 값으로 합니다.

                ini_set('session.cookie_domain', $domain);
                ini_set("session.cookie_lifetime", $this->_session_time); //기본 세션타임 1시간으로 설정 합니다.
                ini_set("session.cache_expire", $this->_session_time); //기본 세션타임 1시간으로 설정 합니다.
                ini_set("session.gc_maxlifetime", $this->_session_time); //기본 세션타임 1시간으로 설정 합니다.
                session_cache_limiter('private');
                $_REQUEST['token'] = trim(strip_tags($_REQUEST['token']));
                // 로그인 정보를 cookie 값에서 가져올때 사용. 작동은 하지만 닫아둡니다. 필요시 사용합니다.
                if($_REQUEST['token']=='' && trim($_COOKIE['token'])!='') {
                    $_REQUEST['token'] = trim($_COOKIE['token']);
                }
                if($_REQUEST['token']!='') {
                    $this->token = $_REQUEST['token'];
                    session_id($_REQUEST['token']); // session_start 전에 전달 받은 값을 session_id로 사용합니다.
                    session_start(); // token을 전달 받지을때만 세션을 사용합니다. 불필요한 세션정보 생성을 막기 위해서입니다.
                    // session_regenerate_id(); // 접속할때마다 token 값을 바꿉니다. 그러나, reactjs 쪽 클라이언트에서 변경된 cookie값을 사용하지 않고 로그인시 생성된 cookie값을 사용하기때문에 적용하지 않음.
                }
				// $_SESSION['time'] = time();
				// var_dump('$_SESSION:', $_SESSION);
            }
        }

        /**
         * Session Open
         */
        public function _session_open(){
            if($this->cache_method == 'memcached' && is_object($this->cache_obj)){
                return true;
            }
            return false;
        }

        /**
        * Session Close
        */
        public function _session_close(){
            $this->cache_obj->quit();
            return true;
        }

        /**
         * session id 생성
         * memcached를 공통으로 사용할 경우 중복키가 발생할 수 있어서 키에 호스트이름과 세션 구분자를 넣어 중복을 피합니다.
         */
        private function _gen_session_id($id){
            return sha1(ini_get('session.cookie_domain').'/session/'.$id);
        }

        /**
         * Session Read
         */
        public function _session_read($id){
            $id = !$id ? $this->token : $id;
            $r = $this->cache_obj->get($this->_gen_session_id($id));
            $r = $r ? unserialize(gzuncompress($r)) : '';
            if(!$r) { // NULL 일경우 write할때 오류발생하며 튕기는 현상있음. 그래서 값이 없는건 아예 지워 버림. 그러면 잘 됨.
                    $this->_session_destroy($id);
            }
            return $r;
        }

        /**
        * Session Write
        */
        public function _session_write($id, $data){
            $v = gzcompress(serialize($data));
            $r = $this->cache_obj->set($this->_gen_session_id($id), $v, time() + $this->_session_time);
            return $r;
        }

        /**
        * Session Destroy
        */
        public function _session_destroy($id){
            $r = $this->cache_obj->delete($this->_gen_session_id($id));
            return $r;
        }

        /**
         * Session Garbage Collection
         */
        public function _session_gc($max){
            return true;
        }



        // --------------------------------------------------------------------- //
        // file

        public function check_dir($dir)
        {
            if(!file_exists($dir)) {
                if (!mkdir($dir, 0777, true)) {
                    $this->error('005', str_replace('{dir}', $dir, $this->__('Failed to create folders. path: {dir}')));
                }
            }
            if (!is_writable($dir)) {
                $this->error('005', str_replace('{dir}', $dir, $this->__('The folder does not have write permission. Please specify write permission. path: {dir}')));
            }
        }


        // --------------------------------------------------------------------- //
        // db

        private function _set_db_info() {
            $this->db_info = __DB_INFO__; // dbinfo 는 config.php에서 설정.
        }

        /**
         * DB 연결
         */
        private function _db_connect($target = '')
        {
            if (is_object($this->_db_link) && $target!='master') {
                return true;
            }
            if (is_object($this->_db_link_master) && $target=='master') {
                return true;
            }
            $db_info = array();
            // select master and slave db info
            $master = $this->db_info['master'];
            $slave = $this->db_info['slave'][ array_rand($this->db_info['slave']) ];
            if($target=='master') {
                $db_info = $master;
            } else {
                $db_info = $slave;
            }
            $_db_link = mysqli_connect($db_info['host'], $db_info['username'], $db_info['password'], $db_info['database']);
            // slave connect 
            if (! $_db_link) {
                $this->error("001", $this->__("Mysql Connection Error: Failed connecting to database server") . "\r\n\r\n".mysqli_connect_error()."");
            }
            $_db_link->db_info = $db_info;
            if($target=='master') {
                $this->_db_link_master = $_db_link;
            } else {
                $this->_db_link_slave = $_db_link;
            }
            $this->_db_link = $_db_link;
            if (!empty($db_info['charset'])) {
                $this->query('SET NAMES "' . $db_info['charset'] . '"', $target);
                mysqli_set_charset($_db_link, $db_info['charset']);
            }
            // MySQL 8 에서 날짜 형식 오류 제외하기
            // SELECT VERSION();
            $this->query("SET @@SESSION.sql_mode = CONCAT_WS(',', @@SESSION.sql_mode, 'ALLOW_INVALID_DATES')");
            // master 연결은 필요할때 쿼리 실행전에 연결하도록 함.(select만 있는경우 불필요하게 2개씩 연결할 필요 없음)
            // 마스터와 슬래이브가 같은 서버인경우 슬래이브 연결을 그대로 사용하기.
            if($target=='' && $master['host'] == $slave['host']) {
                $this->_db_link_master = $this->_db_link_slave;
            }
        }

        /**
         * DB 연결 종료
         */
        private function _db_close($to='')
        {
            if ($this->_db_link) {
                mysqli_close($this->_db_link);
            }
            if($to) {
                $link_name = 'db_link_'.$to;
                unset ( $this->$link_name );
            }
        }
        public function db_close()
        {
            $this->_db_close();
        }

        /**
         * DB transaction start
         */
        public function transaction_start() {
            $this->set_db_link('master');
            mysqli_begin_transaction($this->_db_link, MYSQLI_TRANS_START_READ_WRITE);
        }

        public function transaction_end($action='commit') {
            $this->set_db_link('master');
            if($action=='commit') {
                mysqli_commit($this->_db_link);
            } else {
                mysqli_rollback($this->_db_link);
            }
        }

        /**
         * db query function
         */
        public function query($query, $target='')
        {
            if(! $this->_db_link) {
                $this->_db_connect();
            }
            $sql_type = preg_match("/(insert |delete |update |replace |alter |create )/i", trim($query)) ? 'master' : 'slave';
            $_db_link = $this->_db_link;
            if ($sql_type == 'master' && $target=='' || $target=='master') {
                if(! $this->_db_link_master) {
                    $this->_db_connect('master');
                }
                $_db_link = $this->_db_link_master;
            }
            $t = microtime(true);
            $result = mysqli_query($_db_link, $query);
            $et = microtime(true) - $t;
            if (!$result) {
                $this->error("005", $this->__("Mysql Query Error: Failed executing database query")."<br />\r\nDate/Time: " . date('Y-m-d H:i:s') . "<br />\r\nQuery: $query<br />\r\nMySQL Error: " . mysqli_error($_db_link) . "");
            }
            if ($sql_type == 'master') {
                $this->_recently_query['rows_affected'] = mysqli_affected_rows($_db_link);
                if (preg_match("/^\\s*(insert|replace) /i", $query)) {
                    $this->_recently_query['last_insert_id'] = mysqli_insert_id($_db_link);
                }
                if ($sql_type == 'master' && $target=='' || $target=='master') {
                    if(
                        strpos( $query, 'SET NAMES ' )===false &&
                        strpos( $query, 'SET deposit_check_time=UNIX_TIMESTAMP()' )===false &&
                        strpos( $query, 'INSERT INTO js_member_device SET' )===false &&
                        strpos( $query, '_chart' )===false
                    ) {
                        @ mysqli_query($_db_link, "insert into js_query_log values ( REPLACE(SUBSTR(NOW(),3,8), '-',''), UNIX_TIMESTAMP(), '".$this->escape($_SERVER['PHP_SELF'])."', '".$this->escape($query)."', '".$this->escape($et)."' ) "); // write query log to db
                    }
                }
                $this->write_log($query.','.($result?'success':'fail'));
            } else {
                $this->_recently_query['num_fields'] = @mysqli_num_fields($result);
                $this->_recently_query['num_rows'] = @mysqli_num_rows($result);
                // 1초 이상 걸리는 오래걸리는 쿼리 저장.
				if( $et >= 1 ) { 
                    $md5_sql = md5($query); // 중복 저장을 방지하기 위해 md5 해시값을 PK로 사용함.
                    @ mysqli_query($_db_link, "INSERT IGNORE INTO js_query_slow VALUES ( '{$this->escape($md5_sql)}', REPLACE(SUBSTR(NOW(),3,8), '-',''), UNIX_TIMESTAMP(), '".$this->escape($_SERVER['PHP_SELF'])."', '".$this->escape($query)."', '{$this->numtostr($et)}' ) "); // write query log to db
                }
            }
            return $result;
        }

        /**
         * 사용할 DB를 선택할때 사용.
         * slave를 지정해도 insert/update 쿼리는 마스터로 전송됩니다.
         * 가급적 insert가 있는 로직에서는 master를 선택하세요.
         */
        public function set_db_link($to = 'slave') {
            $to = $to=='master' ? 'master' : 'slave';
            $link_name = 'db_link_'.$to;
            if(!is_object($this->$link_name)) {
                $this->_db_connect($to);
            }
            $this->db_link = $this->$link_name;
        }

        /**
         * 커리 결과를 object로 받기.
         */
        public function _fetch_object($result)
        {
            return mysqli_fetch_object($result);
        }

        /**
         * 커리 결과를 array로 받기.
         */
        public function _fetch_array($result)
        {
            return mysqli_fetch_array($result);
        }

        /**
         * 커리 결과를 array로 받기. 
         */
        public function _fetch_assoc($result)
        {
            return mysqli_fetch_assoc($result);
        }

        /**
         * 쿼리를 실행하고 결과를 Array로 리턴. 단일 row 의 결과를 받을때 사용합니다.
         */
        public function query_fetch_assoc($query, $target='', $time=0)
        {
            $cache = $this->get_cache($query);
            if($cache) {
                $return = $cache;
            } else {
                $result = $this->query($query, $target);
                $return = $this->_fetch_assoc($result);
                $this->_db_free_result($result);
                $this->set_cache($query, $return, $time);
            }
            return $return;
        }

        /**
         * 쿼리를 실행하고 결과를 Array로 리턴. 단일 row 의 결과를 받을때 사용합니다.
         */
        public function query_fetch_array($query, $target='', $time=0)
        {
            $cache = $this->get_cache($query);
            if($cache) {
                $return = $cache;
            } else {
                $result = $this->query($query, $target);
                $return = $this->_fetch_array($result);
                $this->_db_free_result($result);
                $this->set_cache($query, $return, $time);
            }
            return $return;
        }

        /**
         * 쿼리를 실행하고 결과를 Object로 리턴. 단일 row 의 결과를 받을때 사용합니다.
         */
        public function query_fetch_object($query, $target='', $time=0)
        {
            $cache = $this->get_cache($query);
            if($cache) {
                $return = $cache;
            } else {
                $result = $this->query($query, $target);
                $return = $this->_fetch_object($result);
                $this->_db_free_result($result);
                $this->set_cache($query, $return, $time);
            }
            return $return;
        }

        /**
         * 쿼리를 실행하고 결과를 배열 속 Object로 리턴. 여러 row의 결과를 배열로 받을때 사용합니다.
         */
        public function query_list_assoc($query, $target='', $time=0)
        {
            $return = array();
            $cache = $this->get_cache($query);
            if($cache) {
                $return = $cache;
            } else {
                $result = $this->query($query, $target);
                while ($row = $this->_fetch_assoc($result)) {
                    if (!empty($row)) {
                        $return[] = $row;
                    }
                }
                $this->_db_free_result($result);
                $this->set_cache($query, $return, $time);
            }
            return $return;
        }

        /**
         * 쿼리를 실행하고 결과를 배열 속 Object로 리턴. 여러 row의 결과를 배열로 받을때 사용합니다.
         */
        public function query_list_array($query, $target='', $time=0)
        {
            $return = array();
            $cache = $this->get_cache($query);
            if($cache) {
                $return = $cache;
            } else {
                $result = $this->query($query, $target);
                while ($row = $this->_fetch_object($result)) {
                    if (!empty($row)) {
                        $return[] = $row;
                    }
                }
                $this->_db_free_result($result);
                $this->set_cache($query, $return, $time);
            }
            return $return;
        }

        /**
         * 쿼리를 실행하고 결과를 배열 속 Object로 리턴. 여러 row의 결과를 배열로 받을때 사용합니다.
         */
        public function query_list_object($query, $target='', $time=0)
        {
            $return = array();
            $cache = $this->get_cache($query);
            if($cache) {
                $return = $cache;
            } else {
                $result = $this->query($query, $target);
                while ($row = $this->_fetch_object($result)) {
                    if (!empty($row)) {
                        $return[] = $row;
                    }
                }
                $this->_db_free_result($result);
                $this->set_cache($query, $return, $time);
            }
            return $return;
        }

        /**
         * 쿼리 결과를 특정 컬럼 값을 키로 사용하는 object값들의 배열을 리턴합니다. 
         */
        public function query_list_object_column($query, $column_name, $target='', $time=0) {
            $r = array();
            $t = $this->query_list_object($query, $target, $time);
            foreach($t as $row) {
                $r[$row->{$column_name}] = (object) $row;
            }
            return $r;
        }
        /**
         * 쿼리를 실행하고 결과를 배열 속 Object로 리턴. 여러 row의 결과를 배열로 받을때 사용합니다.
         */
        public function query_list_keyvalue($query, $target='', $time=0)
        {
            $return = array();
            $cache = $this->get_cache($query);
            if($cache) {
                $return = $cache;
            } else {
                $result = $this->query($query, $target);
                while ($row = $this->_fetch_array($result)) {
                    if (!empty($row)) {
                        $return[] = array($row[0]=>$row[1]);
                    }
                }
                $this->_db_free_result($result);
                $this->set_cache($query, $return, $time);
            }
            return $return;
        }

        /**
         * 쿼리를 실행하고 결과를 배열 속 Object로 리턴. 여러 row의 결과를 배열로 받을때 사용합니다.
         */
        public function query_list_one($query, $column_cnt=0, $target='')
        {
            $return = array();
            $result = $this->query($query, $target);
            while ($row = $this->_fetch_array($result)) {
                if (!empty($row)) {
                    $return[] = $row[$column_cnt];
                }
            }
            $this->_db_free_result($result);
            return $return;
        }

        /**
         * 쿼리를 실행하고 얻은 결과에서 첫번째 컬럼의 첫번째 row의 결과만 리턴. 단일 값을 바로 받을때 사용합니다.
         */
        public function query_one($query, $target='', $time=0)
        {
            $return = array();
            $cache = $this->get_cache($query);
            if($cache) {
                $return = $cache;
            } else {
                $result = $this->query_fetch_array($query, $target);
                $return = $result[0];
                $this->set_cache($query, $return, $time);
            }
            return $return;
        }

        /**
         * DB 쿼리 결과 초기화
         */
        public function _db_free_result($result)
        {
            if ($result) {mysqli_free_result($result);}
        }

        /**
         * 이스케이프 문자열로 변경. 쿼리에 사용되는 값에 적용하여 SQL Injection에 대비합니다.
         */
        public function escape($v)
        {
            return mysqli_real_escape_string($this->_db_link, $v);
        }

        /**
         * WHERE 절 생성.
         * @param Array WHERE 절에 들어갈 조건
         * 예) 키,값 형식으로 지정. 조건은 AND 로 연결. array('member_no'=>'33', 'member_no'=>'34')  -  1 and member_no='33' and member_no='33'
         * 예) 연결조건까지 지정할때 array(array('op'=>'and', 'col'=>'member_no', 'val'=>'33'), array('op'=>'or', 'col'=>'member_no', 'val'=>'34'))  -  1 and member_no='33' or member_no='33'
         * 예) 혼합조건 array('member_no'=>'33', array('op'=>'or', 'col'=>'member_no', 'val'=>'34'))  -  1 and member_no='33' or member_no='33'
         * 예) 혼합조건 array( 'txn_type'=>array('R','D'), 'status'=>array('op'=>'OR', 'val'=>'O'), 'symbol'=>$symbol, 'address_relative'=>$sender, 'amount'=>$amount );  -  1  AND txn_type IN ("R","D")   OR status="O"  AND symbol=""  AND address_relative=""  AND amount=""
         * @return String WHERE 절
         */
        public function db_gen_sql_where($where = array())
        {
            $sql_where = '1 ';
            if (is_array($where)) {
                foreach ($where as $key => $row) {
                    if(isset($row['op']) && trim($row['op'])!='') {
                        $sql_where .= ' ' . $row['op'] . ' ' . (trim($row['col'])!='' ? $row['col'] : $key) ; // '';
                        $sql_where .= is_array($row['val']) ? ' IN ("'.implode('","', array_map(array($this, 'escape'), $row['val'])).'")  ' : '="' . $this->escape($row['val']) . '" ' ;
                    } else {
                        $sql_where .= ' AND ' . $key ;
                        $sql_where .= is_array($row) ? ' IN ("'.implode('","', array_map(array($this, 'escape'), $row)).'")  ' : '="' . $this->escape($row) . '" ' ;
                    }
                }
            }
            return $sql_where;
        }

        /**
         *
         */
        private function db_gen_sql_set($data = array())
        {
            $sql_data = '';
            if (is_array($data)) {
                foreach ($data as $col => $val) {
                    if(trim($col)!='') {
                        if ($sql_data != '') {$sql_data .= ', ';}
                        $sql_data .=  $col . '="' . $this->escape($val) . '"';
                    }
                }
            }
            return $sql_data;
        }

        /**
         * 특정 테이블의 컬럼명을 추출합니다.
         * @param String $table 디비 테이블 이름
         */
        public function db_get_column($table) {
            $database = $this->_db_link->db_info['database'];
            return $this->query_list_object("SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT  FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '{$this->escape($table)}'  AND TABLE_SCHEMA='{$this->escape($database)}' ");
        }

        /**
         * 특정 테이블에서 다량의 데이터 Row를 추출합니다.
         * @param String $table 디비 테이블 이름
         * @param Array $where 검색조건. 단순 매칭입니다.
         * @param Number $rows 결과 갯수
         * @param Number $offset $rows갯수씩 분류시 몇번째 분류를 가져올지. 0부터 시작.
         */
        public function db_get_list($table, $where = array(), $rows = 0, $offset = 0)
        {
            $where = $this->db_gen_sql_where($where);
            $sql = "SELECT * FROM {$table} WHERE {$where}";
            if($rows) {
                $sql.= " LIMIT {$offset}, {$rows}";
            }
            return $this->query_list_object($sql);
        }

        /**
         * 특정 테이블에서 단일의 데이터 Row를 추출합니다.
         * @param String $table 디비 테이블 이름
         * @param Array $where 검색조건. 단순 매칭입니다.
         */
        public function db_get_row($table, $where = array())
        {
            $where = $this->db_gen_sql_where($where);
            return $this->query_fetch_object("SELECT * FROM {$table} WHERE {$where} LIMIT 1");
        }

        public function db_insert($table, $data)
        {
            $data = $this->db_gen_sql_set($data);
            return $this->query("INSERT INTO {$table} SET {$data}");
        }

        public function db_update($table, $data, $where)
        {
            $data = $this->db_gen_sql_set($data);
            $where = $this->db_gen_sql_where($where);
            return $this->query("UPDATE {$table} SET {$data} WHERE {$where}");
        }

        public function db_delete($table, $where)
        {
            $where = $this->db_gen_sql_where($where);
            return $this->query("DELETE FROM {$table} WHERE {$where}");
        }

        public function check_table_exists($table_name) {
            return !! $this->query_one("SHOW TABLES LIKE '{$this->escape($table_name)}'");
        }
        public function isTable($table_name){
            return $this->check_table_exists($table_name);
        }

        // --------------------------------------------------------------------- //
        // cache

        public $cache_method = 'file';
        public $cache_dir = __SRF_DIR__.'/cache/';
        public $cache_obj = null;
        // public $memcache_info = array();

        private function _set_memcache_info() {
            if(class_exists('Memcached') && $this->cache_method=='file') {
                $memcache_info = __MEMCACHE_INFO__;
                if($memcache_info['host']) {
                    $this->cache_obj = new Memcached();
                    $this->cache_obj->addServer($memcache_info['host'], $memcache_info['port']);
                    $this->cache_method = 'memcached';
                }
            }
        }

        public function set_cache_dir($dir)
        {
            if($this->cache_method=='file') {
                $this->cache_dir = $dir;
                $this->check_dir($this->cache_dir);
            }
        }

        /**
         * 캐시 내용 가져오기.
         * @param String id cache id
         * @param Number sec cache time(sec.)
         */
        public function get_cache($id)
        {
            if($this->cache_method=='memcached') {
                $id = $this->get_cache_file_path($_SERVER['HTTP_HOST'].'/'.$id);
                $r = $this->cache_obj->get($id);
            } else {
                $_cache_file = $this->get_cache_file_path($id);
                if (file_exists($_cache_file)) {
                    $r = file_get_contents($_cache_file);
                }
            }
            $r = $r ? (array) unserialize(gzuncompress($r)) : '';
            if( isset($r['time']) && $r['time'] > time()) {
                $r = $r['contents'];
            } else {
                $r = '';
                $this->delete_cache($id);
            }
            return $r;
        }

        public function set_cache($id, $contents, $sec=60)
        {
            if($sec>0) {
                $v = gzcompress(serialize(array('time'=>time()+$sec, 'contents'=>$contents)));
		        if($this->cache_method=='memcached') {
		            $id = $this->get_cache_file_path($_SERVER['HTTP_HOST'].'/'.$id);
		            $this->cache_obj->set($id, $v, time() + $sec);
		        } else {
		            file_put_contents($this->get_cache_file_path($id), $v);
		        }
            }
            return $contents;
        }

        public function get_cache_file_path($id)
        {
            return $this->cache_dir . '.' . $this->get_cache_file_name($id);
        }

        public function get_cache_file_name($id)
        {
            return md5($id);
        }

        public function delete_cache ($id = null, $sec = 60*60*24) {
            if($this->cache_method=='memcached') {
                if($id) {
                    $this->cache_obj->delete($id);
                } else {
                    $this->cache_obj->flush(); //$sec 사용하지 않고 전부 삭제합니다.
                }
            } else {
                if($id) {
                    $_cache_file = $this->get_cache_file_path($id);
                    if(file_exists($_cache_file)) {unlink($_cache_file);}
                } else {
                    $this->clear_old_file($this->cache_dir, $sec);
                }
            }
        }

        public function clear_old_file($dir, $sec = 60)
        {
            if($this->cache_method=='file') {
                foreach (glob(realpath($dir) . "/.*") as $file) {
                    if(is_dir($file)){continue;}
                    $r = unserialize(gzuncompress(file_get_contents($file)));
                    if( $r['time'] < time()) {
                        @unlink($file);
                    }
                }
            }
        }
//
        // --------------------------------------------------------------------- //
        // remote request

        private $curl = null;
        private $cookieJar = './cookies.txt';
        private $optTimeOut = 30;
        private $optFollowLocation = true;
        private $optReturnTransfer = true;
        private $proxyInfo = array('server'=>'','port'=>'','account'=>''); // http://10.14.10.1:3128 ,  3128, user:pass

        public function setCookieJar ($file) {
            if(!file_exists($file)) {
                file_put_contents($file, '');
            }
            $this->cookieJar = $file;
        }

        public function getCookieJar () {
            return $this->cookieJar;
        }

        public function remote_get($p_url, $p_response_header=false, $p_referer='')
        {
            $this->remote_setup($p_url, $p_response_header, $p_referer);
            $_return = $this->remote_request();
            $this->remote_close();
            return $_return;
        }

        public function remote_post($p_url, $p_fields, $p_response_header=false, $p_referer='')
        {
            $this->remote_setup($p_url, $p_fields, $p_response_header, $p_referer);
            $_return = $this->remote_request();
            $this->remote_close();
            return $_return;
        }

        /**
         * get cookie value by remote request
         * @param url request url. for check domain
         * @param string cookie name.
         * @return string cookie value.
         */
        public function remote_get_cookie($p_url, $p_name) {
            $cookies = array();
            $cookiejar = $this->getCookieJar();
            // var_dump($cookiejar, $this->cookieJar); exit;
            if(file_exists($cookiejar)) {
                $cookies = file($cookiejar);
            }
            foreach($cookies as $cookie) {
                $cookie = explode("\t", $cookie);
                if(strpos($p_url, $cookie[0])!==false && $cookie[5]==$p_name) {
                    return trim($cookie[6]);
                }
            }
            return '';
        }

        function getUserAgent($p_i='') {
            $agents = array();
            $agents[] = 'Mozilla/5.0 (Windows; U; MSIE 7.0; Windows NT 6.0; en-US)';
            $agents[] = 'Opera/9.63 (Windows NT 6.0; U; ru) Presto/2.1.1';
            $agents[] = 'Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US; rv:1.9.0.5) Gecko/2008120122 Firefox/3.0.5';
            $agents[] = 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; WOW64; Trident/4.0; MRA 5.5 (build 02842); SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; InfoPath.2)';
            return $agents[(empty($p_i)?mt_rand(0,count($agents)-1): $p_i)];
        }

        function remote_setup($p_url, $p_fields='', $p_response_header=false, $p_referer='') {
            $this->curl = curl_init();
            curl_setopt($this->curl, CURLOPT_URL, $p_url);
            $header = array();
            $header[] = "Accept: text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
            $header[] = "Cache-Control: max-age=0";
            $header[] = "Connection: keep-alive";
            $header[] = "Keep-Alive: 300";
            $header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
            $header[] = "Accept-Language: ko-kr,ko,en-us,en;q=0.5";
            $header[] = "Pragma: "; // browsers keep this blank.
            if (strpos($p_url, 'https:')===0) {
                curl_setopt ($this->curl, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt ($this->curl, CURLOPT_SSLVERSION,TRUE);
            }
            if($p_response_header) {
                curl_setopt($this->curl, CURLOPT_HEADER, 1);
            }
            curl_setopt($this->curl, CURLOPT_USERAGENT, $this->getUserAgent());
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, $header);
            curl_setopt($this->curl, CURLOPT_COOKIEJAR, $this->cookieJar);
            curl_setopt($this->curl, CURLOPT_COOKIEFILE, $this->cookieJar);
            curl_setopt($this->curl, CURLOPT_AUTOREFERER, FALSE);
            curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, $this->optFollowLocation);
            curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->optTimeOut);
            curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, $this->optReturnTransfer);
            if ( $this->proxyInfo ) {
                curl_setopt($this->curl, CURLOPT_PROXY, $this->proxyInfo['server']); //http://10.14.10.1:3128
                curl_setopt($this->curl, CURLOPT_PROXYPORT, $this->proxyInfo['port']); //3128
                if ( isset($this->proxyInfo['account']) and ! empty($this->proxyInfo['account']) ) {
                    curl_setopt($this->curl, CURLOPT_PROXYUSERPWD, $this->proxyInfo['account']); //user:pass
                }
            }
            if(!empty($p_referer)) {
                curl_setopt($this->curl, CURLOPT_REFERER, $p_referer);
            }
            if(!empty($p_fields)) {
                curl_setopt($this->curl, CURLOPT_POST, 1);
                curl_setopt($this->curl, CURLOPT_POSTFIELDS, $p_fields);
            }
        }

        function remote_request() {
            return curl_exec($this->curl);
        }

        function remote_close() {
            curl_close($this->curl);
        }

        // --------------------------------------------------------------------- //
        // log

        public $log_dir = '';
        public $log_name = 'API';
        public $logging = false;

        public function write_log($msg = '', $clear_old=false)
        {
            $flags = $clear_old ? null : FILE_APPEND;
            if ($this->logging && trim($msg) != '') {
                $r = file_put_contents($this->get_file(), $this->get_log($msg), $flags);
            }
        }

        public function set_log_dir($dir)
        {
            $this->log_dir = $dir;
            $this->check_dir($this->log_dir);
        }
        public function set_log_name($log_name)
        {
            $this->log_name = $log_name;
        }

        public function set_logging($bool)
        {
            $this->logging = $bool ? true : false;
        }
        public function get_logging()
        {
            return $this->logging;
        }

        public function get_file()
        {
            return $this->log_dir . $this->log_name . '_' . date('Ymd') . '.log';
        }

        public function get_log($msg)
        {
            return '[' . date('Y-m-d H:i:s') . '] '.$_SERVER['REMOTE_ADDR'].' ' . basename(dirname($_SERVER['PHP_SELF'])) . ' ' . $msg . PHP_EOL;
        }

        // --------------------------------------------------------------------- //
        // request

        function strip_tags_all($v) {
            if (is_array($v)) {
                foreach($v as $key => $val){
                    $v[$key] = $this->strip_tags_all($val);
                }
            } else {
                $v = trim(strip_tags($v));
            }
            return $v;
        }

        /**
         * 해킹시 사용되는 문구 확인 후 차단.
         * request parameter중에 해킹에 사용되는 이름이 있는경우 애러처리합니다.
         * HcPcEgmp, kXvrbKwH
         */
        public function check_hacker_string($v)
        {
            if (is_array($v)) {
                foreach($v as $key => $val){
                    $v[$key] = $this->check_hacker_string($val);
                }
            } else {
                if(
                    strpos($v, 'sample@email.tst')!==false ||
                    strpos($v, 'HcPcEgmp')!==false ||
                    strpos($v, 'kXvrbKwH')!==false
                ) {
                    $this->error('040', $this->__('Unable to access'));
                }
            }
        }

        /**
         * POST, GET, REQUEST의 값에서 html 태그를 삭제한다.
         * @paran array or string $param_names html을 사용하는 인자 이름들. strip_tags를 하지 않는다.
         */
        public function parameter_clean($param_names = '')
        {
            if (!is_array($param_names)) {
                $param_names = array($param_names);
            }
            foreach ($_POST as $key => $val) {
                $this->check_hacker_string($val);
                if (!in_array($key, $param_names)) {
                    $_POST[$key] = $this->strip_tags_all($val);
                }
            }
            foreach ($_GET as $key => $val) {
                $this->check_hacker_string($val);
                if (!in_array($key, $param_names)) {
                    $_GET[$key] = $this->strip_tags_all($val);
                }
            }
            foreach ($_REQUEST as $key => $val) {
                $this->check_hacker_string($val);
                if (!in_array($key, $param_names)) {
                    $_REQUEST[$key] = $this->strip_tags_all($val);
                }
            }
        }

        public function set_site_code($domain='')
        {
            $code = $this->get_site_code($domain);
            $GLOBALS['__SITECODE__'] = $code;
            if($_COOKIE['sitecode']!=$code) {
                @ setcookie('sitecode', $code, null, '/');
                $_COOKIE['sitecode']=$code;
            }
        }

        public function get_site_code($domain='')
        {
            $code = $GLOBALS['__SITECODE__'];
            if(! $GLOBALS['__SITECODE__']) {
                $domain = $domain ? $domain : $_SERVER['HTTP_HOST'];
                $code = $this->query_one("select code from js_config_site where domain='".$this->escape($domain)."' ");
            }
            return $code;
        }

        /**
         * 웹사이트에서 사용하는 여러 설정 값들을 설정 테이블에서 조회합니다.
         */
        public function get_config($table_name)
        {
            return $this->query_fetch_object("SELECT * FROM {$table_name} WHERE code='{$this->escape($this->get_site_code())}' ");
        }

        // --------------------------------------------------------------------- //
        // email & gmail

        public $send_email_error_msg = '';

        public function send_email($receiver_email, $subject, $body, $run_type='gmail') {
            switch($run_type) {
                default:
                    return $this->send_email_from_gmail($receiver_email, $subject, $body);
            }
        }

        public function send_email_from_gmail($receiver_email, $subject, $body) {
            $this->send_email_error_msg = '';
            include __dir__.('/phpmailer/PHPMailerAutoload.php');
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Mailer = "smtp";

            try {
                // $mail->SMTPDebug  = __API_RUNMODE__=='loc' ? 1 : 0;  
                // SMTP 암호화 여부
                $mail->SMTPAuth = true;
                // SMTP 포트
                $mail->Port = 587;
                // SMTP 보안 프초트콜
                $mail->SMTPSecure = "tls"; // tls 587, ssl 465
                // 구글 smtp 설정
                $mail->Host = 'smtp.gmail.com'; //"smtp.gmail.com";
                // SMTP 계정 이메일
                $sender_email = __GOOGLE_GMAIL_USERNAME__; //"nowonbun@gmail.com";
                // gmail 유저 아이디
                $mail->Username = substr($sender_email, 0, strpos($sender_email, '@'));; //"nowonbun";
                // gmail 패스워드
                $mail->Password = __GOOGLE_GMAIL_PASSWORD__; //"***password**"; naver - 애플리케이션 비밀번호 생성 필요(내정보 > 보안설정 > 2단계 인증 > 관리하기)
                // 인코딩 셋
                $mail->CharSet = 'utf-8';
                $mail->Encoding = "base64";
                
                // 보내는 사람
                $mail->setFrom($sender_email, $mail->Username); //('nowonbun@gmail.com', 'Tester');
                // 받는 사람
                $mail->AddAddress($receiver_email); //("nowonbun@gmail.com", "nowonbun");

                // 본문 html 타입 설정
                $mail->isHTML(true);
                // 제목
                //$mail->Subject = 'Contact US - '.$_SERVER['HTTP_HOST'];//'Here is the subject';
                $mail->Subject = $subject;
                // 본문 (HTML 전용)
                //$mail->Body    = $_POST['message'];//'This is the HTML message body <b>in bold!</b>';
                $mail->MsgHTML($body);
                // 본문 (non-HTML 전용)
                //$mail->AltBody = strip_tags($_POST['message']);//'This is the body in plain text for non-HTML mail clients';
                // // 첨부파일
                // if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == UPLOAD_ERR_OK) {
                //     $mail->AddAttachment($_FILES['attachment']['tmp_name'], $_FILES['attachment']['name']);
                // }
                // 이메일 발송
                $mail->Send();
                //echo "Message has been sent";
                return true;
            } catch (phpmailerException $e) {
                $this->send_email_error_msg = $e->errorMessage();
                return false;
            } catch (Exception $e) {
                $this->send_email_error_msg = $e->getMessage();
                return false;
            }

        }

        // ----------------------------------------------------------------- //
        // util method

        function genRandomString($n=16) {
            $s = str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
            $r = array();
            for($i=0;$i<$n;$i++) {
                $r[]=$s[mt_rand(0,count($s))];
            }
            return implode('', $r);
        }

        function xml_encode($mixed, $domElement=null, $DOMDocument=null) {
            if (is_null($DOMDocument)) {
                $DOMDocument =new DOMDocument;
                $DOMDocument->formatOutput = true;
                xml_encode($mixed, $DOMDocument, $DOMDocument);
                return $DOMDocument->saveXML();
            }
            else {
                // To cope with embedded objects
                if (is_object($mixed)) {
                $mixed = get_object_vars($mixed);
                }
                if (is_array($mixed)) {
                    foreach ($mixed as $index => $mixedElement) {
                        if (is_int($index)) {
                            if ($index === 0) {
                                $node = $domElement;
                            }
                            else {
                                $node = $DOMDocument->createElement($domElement->tagName);
                                $domElement->parentNode->appendChild($node);
                            }
                        }
                        else {
                            $plural = $DOMDocument->createElement($index);
                            $domElement->appendChild($plural);
                            $node = $plural;
                            if (!(rtrim($index, 's') === $index)) {
                                $singular = $DOMDocument->createElement(rtrim($index, 's'));
                                $plural->appendChild($singular);
                                $node = $singular;
                            }
                        }

                        xml_encode($mixedElement, $node, $DOMDocument);
                    }
                }
                else {
                    $mixed = is_bool($mixed) ? ($mixed ? 'true' : 'false') : $mixed;
                    $domElement->appendChild($DOMDocument->createTextNode($mixed));
                }
            }
        }

        /**
         * get country calling code by ip
         * ex) +82
         * var_dump($simplerestful->get_country_calling_code('8.8.8.8'));
         * var_dump($simplerestful->get_country_calling_code('115.88.67.4'));
         */
        function get_country_calling_code($ip) {
            $r = $this->get_country_calling_code_ipapico($ip);
            if(!$r) { $r = $this->get_country_calling_code_ipstackcom($ip); }
            // if(!$r) { $r = $this->get_country_calling_code_db($ip); }
            $r = $r && strpos($r, '+')===false ? '+'.$r : $r;
            return $r;
        }
        /**
         * ipapi.co limit: Up-to 1000 / day for free
         */
        public function get_country_calling_code_ipapico($ip) {
            $r = $this->remote_get("https://ipapi.co/{$ip}/country_calling_code/?t=".(round(time()/60)), false, '', 60*60*24*31); // 1일 캐시
            return !$r || $r =='Undefined' ? '' : $r;
        }
        /**
         * ipstack.com limit: Up-to 10000 / month for free.
         */
        function get_country_calling_code_ipstackcom($ip) {
            try {
                $r = $this->get_ipstackcom($ip);
                $r = $r->location->calling_code;
            } catch (Exception $e) {
                $r = '';
            }
            return $r ? $r : '';
        }

        /**
         * get country code (2-alphabetic) by ip
         * ex)US, KR
         * var_dump($simplerestful->get_country_code('8.8.8.8'));
         * var_dump($simplerestful->get_country_code('115.88.67.4'));
         */
        function get_country_code($ip) {
            $r = $this->get_country_code_ipapico($ip);
            if(!$r) { $r = $this->get_country_code_ipstackcom($ip); }
            return $r;
        }
        /**
         * ipapi.co limit: Up-to 1000 / day for free
         */
        function get_country_code_ipapico($ip) {
            $r = $this->remote_get("https://ipapi.co/{$ip}/country/?t=".(round(time()/60)), false, '', 60*60*24*31); // 1일 캐시
            return !$r || strtolower($r) =='undefined' ? '' : $r;
        }
        /**
         * ipstack.com limit: Up-to 10000 / month for free.
         */
        function get_country_code_ipstackcom($ip) {
            try {
                $r = $this->get_ipstackcom($ip);
                $r = $r->country_code;
            } catch (Exception $e) {
                $r = '';
            }
            return $r ? $r : '';
        }

        /**
         * ipstack.com limit: Up-to 10000 / month for free.
         * account: kennhan.scc@gmail.com / qwer1234QWER
         * 사용량확인: https://ipstack.com/usage
         */
        function get_ipstackcom($ip) {
            $r = $this->remote_get("http://api.ipstack.com/{$ip}?access_key=6e4246e7f86dcdd2fa7794ca84120767&t=".(round(time()/60)), false, '', 60*60*24*31); // 31일 캐시
            return $r ? (object) json_decode($r) : false;
            // {
            //     "ip": "115.88.67.4",
            //     "type": "ipv4",
            //     "continent_code": "AS",
            //     "continent_name": "Asia",
            //     "country_code": "KR",
            //     "country_name": "South Korea",
            //     "region_code": "41",
            //     "region_name": "Gyeonggi-do",
            //     "city": "Anyang-si",
            //     "zip": "431-700",
            //     "latitude": 37.39870071411133,
            //     "longitude": 126.95939636230469,
            //     "location": {
            //         "geoname_id": 1846898,
            //         "capital": "Seoul",
            //         "languages": [{
            //                 "code": "ko",
            //                 "name": "Korean",
            //                 "native": "\ud55c\uad6d\uc5b4"
            //             }
            //         ],
            //         "country_flag": "http:\/\/assets.ipstack.com\/flags\/kr.svg",
            //         "country_flag_emoji": "\ud83c\uddf0\ud83c\uddf7",
            //         "country_flag_emoji_unicode": "U+1F1F0 U+1F1F7",
            //         "calling_code": "82",
            //         "is_eu": false
            //     }
            // }

        }

        /**
         * 마이크로 타임(초) 숫자 생성
         * Unix Timestamp 기반 소숫점 6자리 숫자 생성 후 소숫점 제거.  1630574144.670157  --> 1630574144670157
         */
        function gen_microtime() {
            return str_replace('.','',sprintf('%01.6f', array_sum(explode(' ',microtime())))); // 16자리 milliseconds
        }

        /**
         * 중복없는 아이디(대문자) 생성
         * @param String $prefix. 접두사
         * @param String $subfix. 점미사
         */
        function gen_id($prefix='', $subfix='') {
            $time = $this->gen_microtime();
            return strtoupper($prefix.base_convert($time, 10, 36).$subfix); // 36진법으로 변경
        }

        /**
         * 숫자만 남깁니다. 빈값은 0으로 변경합니다.
         * @param string $n 숫자형 문자열. 123,456,789 -> 123456789, '' -> 0, null -> 0, -123456789 -> -123456789
         * @return string 숫자만남은 문자열.
         */
        public function clean_number($n) {
            $n = preg_replace('/[^0-9.\-\+]/', '', $n.'');
            return @ ($n*1).'';
        }

        // ----------------------------------------------------------------- //
        // Google Drive

        private $_app_name = __APP_NAME__;
        private $_client = null;
        private $_drive_service = null;
        private $_target_folderid = __GOOGLE_DRIVE_TARGET_FOLDERID__; // set config.php
        private $_credentialsFile = __GOOGLE_DRIVE_CREDENTIALSFILE__; // set config.php

        /**
         * save file to Google Drive
         * api/v1.0/upload/index.php 에서 사용하는 메소드입니다.
         */
        public function save_file_to_google_drive($files) {
            $this->init_google_drive();
            if(! is_array($files['name'])) {
                $files['name'] = array($files['name']);
                $files['type'] = array($files['type']);
                $files['tmp_name'] = array($files['tmp_name']);
                $files['error'] = array($files['error']);
                $files['size'] = array($files['size']);
            }
            $r = array();
            for($i=0; $i<count($files['name']); $i++) {
                $file_size = $files['size'][$i];
                $file_type = $files['type'][$i];
                $file_sourcefile = $files['tmp_name'][$i];
                $file_old_name = $files['name'][$i];
                $file_name = hash('sha256', basename($file_old_name).'-'.time().mt_rand(111111,999999));
                $file_ext = end(explode('.',$file_old_name));
                $new_file = dirname($file_sourcefile).'/'.$file_name.'.'.$file_ext;
                if(!file_exists($file_sourcefile)) {var_dump($file_sourcefile); exit(' file not found. - save_file_to_google_drive');}
                rename($file_sourcefile, $new_file);

                // file check (100MB 미만 파일만 가능)
                if($file_size > 0 && $file_size < 1024*1024*100 && file_exists($new_file)) {
                    
                    // image orientation - new ImageManager 라이브 로드 애러 발생
                    // if (strpos($file_type, 'image/')!==false && extension_loaded('imagick')) {
                    //     try{
                    //         $manager = new ImageManager(array('driver' => 'imagick'));
                    //         $img = $manager->make($new_file)->orientate();
                    //         $img->save($new_file);
                    //     } catch(Exception $e) {
                    //         // 다양한 애러가 나더라도 중요하지 않으니 패스합니다.
                    //     }
                    // }

                    $fileurl = '';
                    $fileid = $this->upload_google_drive($new_file);
                    if($fileid) {
                        $fileurl = $this->view_url_google_drive($fileid);
                    }
                    $r[] = array('name'=>$file_old_name, 'url'=>$fileurl);
                } else {
                    $r[] = array('name'=>$file_old_name, 'url'=>'');
                }
                unlink($new_file);
            }
            return $r;
        }

        /**
         * 구글 드라이브 준비(로그인)
         * @param String 구글드라이브계정정보가 담긴 키파일.
         */
        public function init_google_drive($credentialsFile='')
        {
            if(!$this->_client) {
                $this->_client = new Google\Client();
                if(!$this->_client) {
                    throw new RuntimeException('구글 드라이브 라이브러리가 필요합니다! "composer require google/apiclient:^2.0" 명령어로 설치해주세요.');
                }
                $credentialsFile = $credentialsFile ? $credentialsFile : __DIR__.'/'.$this->_credentialsFile;
                if (!file_exists($credentialsFile)) {
                    throw new RuntimeException('Service account credentials을 찾을 수 없습니다! $credentialsFile:'.$credentialsFile);
                }
                $this->_client->setAuthConfig($credentialsFile);
                $this->_client->setApplicationName($this->_app_name);
                $this->_client->setScopes(Google\Service\Drive::DRIVE);
                $this->_drive_service = new Google\Service\Drive($this->_client);
            }
        }

        /**
         * 구글 드라이브 파일 업로드
         * @param String $file 업로드할 파일(경로포함)
         * @param String $target_folderid 업로드할 구글드라이브 폴더의 아이디. 미지정시 브라우저에서 확인 불가합니다.
         * @return String 파일아이디. 오류시 빈값.
         */
        public function upload_google_drive($file, $target_folderid='')
        {
            $target_folderid = $target_folderid ? $target_folderid : $this->_target_folderid;
            $file_mimetype = mime_content_type($file);
            $file_name = basename($file);

            // 링크가 있는 모든 사람이 볼수 있는 permission 정보 설정
            $anyoneReaderPermission = new Google\Service\Drive\Permission();
            $anyoneReaderPermission->setType('anyone');
            $anyoneReaderPermission->setRole('reader');

            // 드라이브에 업로드할 파일의 정보 설정.
            $DriveFile = new Google\Service\Drive\DriveFile();
            $DriveFile->name = $file_name; //"Big File";// var_dump($DriveFile->name);
            $DriveFile->setMimeType($file_mimetype); // plain/text, image/jpeg// var_dump($DriveFile->mimeType);
            $DriveFile->setPermissions($anyoneReaderPermission); // var_dump($DriveFile->permissions);
            if($target_folderid) $DriveFile->setParents(array($target_folderid)); // 저장될 폴더 아이디 folderId // 수동으로 관리할때 필요합니다. 드라이브 url에서 확인할수 있습니다. 예: https://drive.google.com/drive/u/0/folders/1N0fNpCtBnTetQVMS8fKuZmtrIC9TvXbl

            // 파일 생성
            $chunkSizeBytes = 1 * 1024 * 1024;
            $this->_client->setDefer(true); // 미디어 업로드로 API를 호출하고 즉시 반환하지 않으므로 연기하지 않습니다.
            $request = $this->_drive_service->files->create($DriveFile);

            // 미디어 파일 업로드를 작성하여 업로드 프로세스를 나타냅니다.
            $media = new Google\Http\MediaFileUpload(
                $this->_client,
                $request,
                $file_mimetype,
                null,
                true,
                $chunkSizeBytes
            );
            $media->setFileSize(filesize($file));

            // 다양한 청크를 업로드하십시오. 프로세스가 완료 될 때까지 $status는 false입니다.
            $status = false;
            $handle = fopen($file, "rb");
            while (!$status && !feof($handle)) {
                // $file에서 $chunkSizeBytes를 얻을 때까지 읽으십시오
                // 스트림이 버퍼링 된 경우에는 스트림이 읽혀지고 일반 파일을 나타내지 않으면 프리드가 8192 바이트 이상을 반환하지 않습니다.
                // 읽기 버퍼링 된 파일의 예는 URL에서 읽을 때입니다.
                $chunk = $this->_readVideoChunk_google_drive($handle, $chunkSizeBytes);
                $status = $media->nextChunk($chunk);
            }

            // $status의 최종 값은 업로드 된 객체의 API의 데이터가됩니다.
            $fileid = '';
            if ($status != false) {
                $fileid = $status->getId();
                $this->_drive_service->permissions->create($fileid, $anyoneReaderPermission);
            }

            return $fileid;
        }

        /**
         * 파일 검색 - 파일이름 검색 결과를 받습니다.
         * @param String $name 검색어. 단어로 분리해  "and", "contains" 으로 검색합니다.
         * @return Array 드라이브파일 객체가 담겨있는 배열형식의 결과를 리턴합니다.
         * 다른 검색 예: https://developers.google.com/drive/api/v3/search-files?hl=en#query_string_examples
         * 검색 API: https://developers.google.com/drive/api/v3/reference/files/list?hl=en
         */
        public function search_google_drive($name='')
        {
            $this->init_google_drive();
            $files = array();
            $query = array("mimeType != 'application/vnd.google-apps.folder'"); // 파일만 검색
            if($name) {
                $name = explode(' ', $name);
                $name = array_map(function($v){ return "name contains '{$v}'";}, $name); // 이름이 포함된 파일 검색
                $query[] = implode(' and ', $name); // AND 검색. 즉, 단어가 모두 있어야 함.
            }
            $filter = array('q'=>implode(' and ', $query));
            $list = $this->_drive_service->files->listFiles($filter);
            return $list ? $list->getFiles() : null;
        }

        /**
         * 구글드라이브 파일 삭제
         */
        public function delete_google_drive($fileid)
        {
            $this->init_google_drive();
            try {
                $r = $this->_drive_service->files->delete($fileid);
                $r = isset($r->error) ? false : true;
            } catch (Exception $e) {
                $r = false;
            }
            return $r;
        }
        public function delete_google_drive_by_url($view_url)
        {
            $this->init_google_drive();
            $defualt_view_url = $this->view_url_google_drive();
            $fileid = str_replace($defualt_view_url, '', $view_url);
            try {
                $r = $this->_drive_service->files->delete($fileid);
                $r = isset($r->error) ? false : true;
            } catch (Exception $e) {
                $r = false;
            }
            return $r;
        }

        /**
         * 구글드라이브 전체 파일 삭제
         */
        public function delete_all_google_drive()
        {
            $this->init_google_drive();
            $files = array();
            $filter = array('q'=>"mimeType != 'application/vnd.google-apps.folder'"); // 파일만 검색
            $files = $this->_drive_service->files->listFiles($filter)->getFiles();
            foreach($files as $file) {
                try {
                    $this->_drive_service->files->delete($file->getId());
                } catch (Exception $e) {}
            }
        }


        public function view_url_google_drive($fileid = '')
        {
            return 'https://drive.google.com/uc?export=view&id=' . $fileid;
        }

        private function _readVideoChunk_google_drive ($handle, $chunkSize) {
            $byteCount = 0;
            $giantChunk = "";
            while (!feof($handle)) {
                // fread will never return more than 8192 bytes if the stream is read buffered and it does not represent a plain file
                $chunk = fread($handle, 8192);
                $byteCount += strlen($chunk);
                $giantChunk .= $chunk;
                if ($byteCount >= $chunkSize)
                {
                    return $giantChunk;
                }
            }
            return $giantChunk;
        }

        /**
         * 배열을 특정 컬럼값을 키로하는 객체로 변경하는 함수
         * @param Array 변경하고 싶음 배열
         * @param String 컬럼 이름. 없으면 그냥 객체로 변경.
         * @return Object 변경된 오브젝트.
         */
        public function convert_array_to_object($array, $key_name='') {
            $r = array();
            foreach($array as $row) {
                if($key_name) {
                    $r[$array[$key_name]] = $row;
                } else {
                    $r[] = $row;
                }
            }
            return (object) $r;
        }

        // ----------------------------------------------------------------- //
        // AWS

        private $S3_KEY = 'AKIA42T7UG7KZBISQB7Z'; // kmcse 전용 회원 Access key 
        private $S3_SECRET_KEY = 'jMktTFwipepeWMp1m1XNnVb4D0iH8p7McHEl8nog'; // kmcse 전용 회원 Secret key 
        PRIVATE $S3_REGION = 'ap-northeast-2';
        PRIVATE $S3_BUCKET = 'teaplat';

        public function delete_file_to_s3($objecturl) {

            // 업로드된 파일 S3에 저장하기.
            $bucket = $this->S3_BUCKET;

            $s3 = new S3Client([
                'version' => 'latest',
                'region'  => $this->S3_REGION, // https://docs.aws.amazon.com/ko_kr/general/latest/gr/rande.html
                'credentials' => array( // 이렇게 해야 합니다. 아니면 아래 putObject 부분에서 curl 애러가 발생하며 접속도 못합니다.
                    'key' => $this->S3_KEY,
                    'secret' => $this->S3_SECRET_KEY
                )
            ]);

            // https://kmcse.s3.ap-northeast-2.amazonaws.com/tmp/201807/5ca75e71eba894e05f72e8f7918e41c48b1d29d4b55c2b6d7ac026e633f8432e.png
            $keyname = substr($objecturl, strpos($objecturl, '.amazonaws.com/')+strlen('.amazonaws.com/'));
            try {
                $result = $s3->deleteObject(array(
                    'Bucket' => $bucket,
                    'Key' => $keyname,
                ));
            } catch (S3Exception $e) {
                // echo $e->getMessage() . PHP_EOL;
                return false;
            }

            return $result['DeleteMarker'];

        }

        public function copy_tmpfile_to_s3($objecturl) {

            // 업로드된 파일 S3에 저장하기.
            $bucket = $this->S3_BUCKET;

            $s3 = new S3Client([
                'version' => 'latest',
                'region'  => $this->S3_REGION, // https://docs.aws.amazon.com/ko_kr/general/latest/gr/rande.html
                'credentials' => array( // 이렇게 해야 합니다. 아니면 아래 putObject 부분에서 curl 애러가 발생하며 접속도 못합니다.
                    'key' => $this->S3_KEY,
                    'secret' => $this->S3_SECRET_KEY
                )
            ]);

            $oldkeyname = substr($objecturl, strpos($objecturl, 'tmp/'));
            $keyname = str_replace('tmp/', '', $oldkeyname);

            try {
                $result = $s3->copyObject(array(
                    'Bucket' => $bucket,
                    'Key' => $keyname,
                    'CopySource'=> "{$bucket}/{$oldkeyname}",
                    'ACL'    => 'public-read' // file permission
                ));
            } catch (S3Exception $e) {
                // echo $e->getMessage() . PHP_EOL;
                return false;
            }

            return $result['ObjectURL'];

        }

        public function move_tmpfile_to_s3($objecturl) {

            // 업로드된 파일 S3에 저장하기.
            $bucket = $this->S3_BUCKET;

            $s3 = new S3Client([
                'version' => 'latest',
                'region'  => $this->S3_REGION, // https://docs.aws.amazon.com/ko_kr/general/latest/gr/rande.html
                'credentials' => array( // 이렇게 해야 합니다. 아니면 아래 putObject 부분에서 curl 애러가 발생하며 접속도 못합니다.
                    'key' => $this->S3_KEY,
                    'secret' => $this->S3_SECRET_KEY
                )
            ]);

            $oldkeyname = substr($objecturl, strpos($objecturl, 'tmp/'));
            $keyname = str_replace('tmp/', '', $oldkeyname);

            try {
                $result = $s3->copyObject(array(
                    'Bucket' => $bucket,
                    'Key' => $keyname,
                    'CopySource'=> "{$bucket}/{$oldkeyname}",
                    'ACL'    => 'public-read' // file permission
                ));
            } catch (S3Exception $e) {
                // echo $e->getMessage() . PHP_EOL;
                return false;
            }

            $this->delete_file_to_s3($objecturl);

            return $result['ObjectURL'];

        }

        /**
         * save file to S3
         */
        public function save_file_to_s3($files) {

            // 업로드 파일 배열로 정리.
            // $_FILES 변수에 정의되는 형식으로 전달 받게 됩니다.
            // file_data[] 이름으로 보낼때 $files 구조는 다음과 같습니다.
            //     array(5) {
            //       ["name"]=>array(1) {[0]=>string(9) "kenny.png"}
            //       ["type"]=>array(1) {[0]=>string(9) "image/png"}
            //       ["tmp_name"]=>array(1) {[0]=>string(24) "C:\xampp\tmp\php7CAF.tmp"}
            //       ["error"]=>array(1) {[0]=>int(0)}
            //       ["size"]=>array(1) {[0]=>int(132929)}
            //     }
            // file_data 이름으로 보낼때 $files 구조는 다음과 같습니다.
            //     array(5) {
            //       ["name"]=>string(9) "kenny.png"
            //       ["type"]=>string(9) "image/png"
            //       ["tmp_name"]=>string(23) "C:\xampp\tmp\phpD7C.tmp"
            //       ["error"]=>int(0)
            //       ["size"]=>int(132929)
            //     }

            if(! is_array($files['name'])) {
                $files['name'] = array($files['name']);
                $files['type'] = array($files['type']);
                $files['tmp_name'] = array($files['tmp_name']);
                $files['error'] = array($files['error']);
                $files['size'] = array($files['size']);
            }

            // 업로드된 파일 S3에 저장하기.
            $bucket = $this->S3_BUCKET;

            $s3 = new S3Client([
                'version' => 'latest',
                'region'  => $this->S3_REGION, // https://docs.aws.amazon.com/ko_kr/general/latest/gr/rande.html
                'credentials' => array( // 이렇게 해야 합니다. 아니면 아래 putObject 부분에서 curl 애러가 발생하며 접속도 못합니다.
                    'key' => $this->S3_KEY,
                    'secret' => $this->S3_SECRET_KEY
                )
            ]);

            $r = array();
            // try {
                for($i=0; $i<count($files['name']); $i++) {

                    $file_type = $files['type'][$i];
                    $file_size = $files['size'][$i];
                    $file_sourcefile = $files['tmp_name'][$i];
                    $file_old_name = $files['name'][$i];
                    $file_name = hash('sha256', basename($file_old_name).'-'.time().mt_rand(111111,999999));
                    $file_ext = end(explode('.',$file_old_name));
                    $keyname = 'tmp/'.date('Ym').'/'.$file_name.'.'.$file_ext;

                    // file check
                    if($file_size > 0 && file_exists($file_sourcefile)) {

                        // image orientation
                        if (strpos($file_type, 'image/')!==false && extension_loaded('imagick')) {
                            try{
                                $manager = new ImageManager(array('driver' => 'imagick'));
                                $img = $manager->make($file_sourcefile)->orientate();
                                $img->save($file_sourcefile);
                            } catch(Exception $e) {
                                // 다양한 애러가 나더라도 중요하지 않으니 패스합니다.
                            }
                        }

                        // Upload data.
                        $result = $s3->putObject([
                            'Bucket' => $bucket,    // file storeage
                            'Key'    => $keyname,   // file name
                            'SourceFile' => $file_sourcefile, // file contents
                            'ACL'    => 'public-read' // file permission
                        ]);
                        // Print the URL to the object.
                        // echo $result['ObjectURL'] . PHP_EOL;
                        // $r[] = array('file'=>$file, 'ur'=>$result['ObjectURL']);
                        $r[] = array('name'=>$file_old_name, 'url'=>$result['ObjectURL']);
                    } else {
                        $r[] = array('name'=>$file_old_name, 'url'=>'');
                    }

                }
                // if(count($r)==1) {
                //     $r = $r[0];
                // };
            // } catch (S3Exception $e) {
            //     var_dump($result, $e);
            //     exit('1');
            //     // echo $e->getMessage() . PHP_EOL;
            //     return false;
            // }

            return $r;

        }

        /**
         * 외부 저장소에 저장된 파일을 삭제하기
         * @param String $url 파일 URL
         * @return boid
         */
        public function delete_external_file($url) {
            if(strpos($url, '.amazonaws.com/')!==false)  {
                $this->delete_file_to_s3($url);
            }
            if(strpos($url, 'drive.google.com')!==false)  {
                $this->delete_google_drive_by_url($url);
            }
        }
        
        // ----------------------------------------------------------------- //
        // twitter
        public function write_msg_twitter ($msg) {
            require __DIR__.'/twitter-php/twitter.class.php';
            $twitter = new Twitter(CONSUMER_KEY, CONSUMER_SECRET, ACCESS_TOKEN, ACCESS_TOKEN_SECRET);
            // var_dump(file_exists(__DIR__.'/Diana Krall.jpg'), __DIR__.'/Diana Krall.jpg');
            // exit;
            // $r = $twitter->send('NFTN에서 새로운 NFT을 확인하세요. https://www.nft-n.com/detail.html?goods_idx=G44OGT3EXV&t='.time()); // __DIR__.'/test.png'
            // return $twitter->send('엔에프티엔(www.nft-n.com)에서 새로운 NFT을 확인하세요. https://www.nft-n.com/detail.html?goods_idx=G44OGT3EXV&t='.time()); // __DIR__.'/test.png'
            return $twitter->send($msg); // __DIR__.'/test.png'
        }


        // --------------------------------------------------------------------- //
        // slack

        /**
         * send a message to slack
         * https://dev.to/phpprofi/slack-send-simple-message--3pj7
         *
         * @param string message
         * @param string target chennel or username. to send chennel then use # prefix. to send user then use @ prefix.
         * @param string sender display name.
         * @param url webhook url. Create incoming webhook: https://my.slack.com/services/new/incoming-webhook
         * @return boolean send result. true: success, false: fail.
         */
        public function send_slack_msg($msg='', $receiver='#sandbox', $sender_name='kmcse_trade', $icon_emoji = ':rotating_light:', $webhook_url='https://hooks.slack.com/services/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') {
            $postData = array(
                'payload'    => json_encode(array(
                    'text' => $msg,
                    'channel' => $receiver,
                    'username' => $sender_name,
                    'icon_emoji' => $icon_emoji
                ))
            );
            // payload={"text": "This is a line of text in a channel.\nAnd this is another line of text."}
            // curl -X POST --data-urlencode "payload={\"channel\": \"#sandbox\", \"username\": \"webhookbot\", \"text\": \"This is posted to #sandbox and comes from a bot named webhookbot. <https://alert-system.com/alerts/1234|Click here>\", \"icon_emoji\": \":ghost:\"}"https://hooks.slack.com/services/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
            $ch = curl_init($webhook_url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST,  'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS,     $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $result = curl_exec($ch);
            curl_close($ch);
            return $result;
        }

        // --------------------------------------------------------------------- //
        // response

        /**
         * Whether to terminate API output.
         */
        public $stop_process = true;

        public function set_stop_process($v) {
            $this->stop_process = $v ? true: false;
            $GLOBALS['simplerestful']->stop_process = $v ? true: false;
        }
        public function get_stop_process() {
            return $this->stop_process;
        }

        /**
         * API Transaction ID
         */
        private $tr_id = '';

        public function get_tr_id() {
            return $this->tr_id;
        }
        public function set_tr_id($tr_id) {
            $this->tr_id = trim($tr_id);
        }
        public function gen_tr_id($tmpkey='') {
            $this->tr_id = sha1(session_id().'/'.$tmpkey.'/'.time().mt_rand(111111,999999));
        }

        public function error($code, $msg, $return_type = 'json')
        {
            $r = array(
                'success' => false,
                'error' => array(
                    'code' => $code,
                    'message' => $msg,
                ),
                'id' => $this->get_tr_id()
            );
            if($this->stop_process) {
                $this->response($r, $return_type, true);
            } else {
                throw new Exception(json_encode($r));
            }
        }

        public function success($payload=null, $return_type = 'json')
        {
            if($return_type=='datatable') { // datatable 은 고유의 형식으로 리턴해야 해서 payload에 담지않고 그냥 리턴합니다.
                $r = $payload;
            } else {
                $r = array('success' => true,'id' => $this->get_tr_id() );
                if($payload!==null) { $r['payload'] = $payload; } // payload 전달 값이 없는 경우만 제외하고 나머지는 전달 받은 값 그대로 넘겨준다.
            }
            $this->response($r, strtolower($return_type), $this->stop_process); // success 함수 후 계속 진행할지 종료할지 외부에서 결정 할 수 있도록 변경.
        }

		public function change_false_to_blank($d)
		{
			if (is_array($d) || is_object($d)) {
				$is_object = is_object($d);
				foreach ($d as $key => $val) {
					if (is_array($d)) {
						$d[$key] = $this->change_false_to_blank($val);
					} else {
						$d->{$key} = $this->change_false_to_blank($val);
					}
				}
				return $d;
			} else {
				if (!$d && $d != '0') {
					return '';
				} else {
					return $d;
				}
			}
		}

		public function change_null_to_blank($d) {
			return $this->change_false_to_blank($d);
		}

        /**
         * API 결과값을 출력한다.
         * @global boolean $stop_log 로그 남길지 말지 여부.기본 남김(false값).
         * @param array $data 출력 내용
         */
        public function response($data = array(), $return_type = 'json', $stop_process = false)
        {
			$return_type = $return_type ? $return_type : $this->return_type;
            $data = $this->change_false_to_blank($data);
            // header & response contents 변경.
            switch($return_type) {
                case 'datatable' :
                case 'json' :
                    header('Content-Type: application/json');
                    $response = is_array($data) ? (empty($data) ? '[]' : json_encode($data)) : '[]';
                    break;
                case 'xml' :
                    header('Content-Type: application/xml');
                    if(!empty($data)){
                        $response = xml_encode($data);
                    }
                    break;
                case 'tsv' :
                    header('Content-Type: text/tsv');
                    if(!empty($data)){
                        $response = is_array($data) ? $data['payload'] : $data;
                    }
                    break;
                case 'csv' :
                    header('Content-Type: text/csv');
                    if(!empty($data)){
                        $response = is_array($data) ? $data['payload'] : $data;
                    }
                    break;
                case 'html' :
                    header('Content-Type: text/html');
                    if(!empty($data)){
                        $response = is_array($data) ? $data['payload'] : $data;
                    }
                    break;
            }

            // charset 변경.
            if (!empty($charset) && strtoupper($charset) != 'UTF-8') {
                $response = $this->change_charset($charset, $response);
            }

            // write log
            $this->write_log("RESPONSE: " . $response);

            // 출력
            ob_clean();
            if (!empty($charset) && strtoupper($charset) != 'UTF-8') {
                echo urldecode($response);
            } else {
                echo $response;
            }

            // 작업 종료
            if ($stop_process) {
                exit;
            }

        }

        public function change_charset($charset, $data)
        {
            foreach ($data as $key => $row) {
                if (is_object($row)) {
                    $row = (array) $row;
                }
                if (is_array($row)) {
                    $data[$key] = $this->change_charset($charset, $row);
                } else {
                    $data[$key] = urlencode(iconv('UTF-8', $charset, $row));
                }
            }
            return $data;
        }

        // --------------------------------------------------------------------- //
        // Number

        /**
         * convert number to string
         * 10 * 0.000000000000000111 -> '10.000000000000000111'
         * 0.000000000000000111 -> '0.000000000000000111'
         * 111000000000000 * pow(10,18) -> "111000000000000000000000000000000"
         * 111000000000000 -> "111000000000000"
         * 4.0E-5 -> "0.00004"
         */
        public function numtostr($n) {
            $decimals = 0;
            $sign = '+';
            $s = strval($n);
            // 10승 확인.
            if(strpos($s, 'E')!==false) {
                $t = explode('E',$s);
                $number = $t[0];
                $decimals = substr($t[1],1);
                $sign = substr($t[1],0,1);
                // 소숫점 확인
                if(strpos($number, '.')!==false) {
                    $t = explode('.',$number);
                    $number = $t[0].$t[1];
                    if($sign=='+') {
                        $decimals -= strlen($t[1]);
                    } else {
                        $decimals -= strlen($t[0]);
                    }
                }
            } else {
                $number = $s;
            }
            if($sign=='+') {
                $s = $number . str_repeat('0',$decimals);
            } else {
                $s = '0.'.str_repeat('0',$decimals).$number;
            }
            // 소수점 뒷 0 제거.
            if(strpos($s, '.')!==false) {
                $t = explode('.', $s);
                $number = $t[0];
                $decimal = $t[1];
                $len = strlen($decimal);
                if(preg_match('/[1-9]/', $decimal)) {
                    for($i=1; $i<=$len; $i++) {
                        if(substr($decimal, $i*-1, 1)!=0) {
                            $decimal = substr($decimal, 0, $len-$i+1);
                            break;
                        }
                    }
                    $decimal = '.'.$decimal;
                } else {
                    $decimal = '';
                }
                $s = $number.$decimal;
            }
            return $s;
        }

        /**
         * 서비스하는 지역에 대한 정보를 db에 저장해서 가져오는 방식입니다.
         * 외부 Api가 전혀 작동 안할때 최소한의 보류로 작동시킵니다.
         */
        public function get_country_calling_code_db($ip) {
            $code = $this->get_country_code($ip);
            $r = $code ? $this->query_one("select callling_code from js_country where code='{$code}' ") : '';
            return !$r || $r =='Undefined' ? '' : $r;
        }

        public function get_country() {
            $sql = "SELECT `code`, `name`, `calling_code`, `time_zone` FROM js_country";
            return $this->query_list_object($sql);
        }

        public function set_language_by_countrycode($code) {
            $r = $this->query_one("SELECT `language` FROM js_country where code='{$this->escape($code)}'");
            $r = $r ? $r : 'ko';
            return $r;
        }

        // ----------------------------------------------------------------- //
        // validator method

        public $parameter_name = '';

        function loadParam($name)
        {
            $this->parameter_name = $name;
            return $_REQUEST[$name];
        }
        function displayParamName()
        {
            // exit($this->parameter_name);
            return $this->parameter_name ? '['.$this->parameter_name.'] ' : '';
        }

        function setDefault($s, $val)
        {
            if (trim($s.'') == '' || $s=='undefined') {
                $s = $val;
            }
            return $s;
        }

        function checkEmpty($s, $name = '')
        {
            $name = trim($name);
            $name = $name=='' ? $this->__('the required fields') : $name;
            if (trim($s) == '' || $s=='undefined') {
                $this->error('003', str_replace('{name}', $name, $this->__("Please fill in {name}.")));
            }
            return $s;
        }

        function checkZero($s)
        {
            if (trim($s)*1 == '0') {
                $this->error('004', $this->__("Please enter a number greater than zero."));
            }
            return $s;
        }

        function checkNumber($s)
        {
            if (preg_match('/[^\-\+0-9.]/', $s)) {
                $this->error('002', $this->__("Please enter the number."));
            }
            return $s;
        }

        /**
         * validate api transaction id
         * @param String api transaction id
         * @return Boolean validate result. true: valid. false: invalid
         */
        function isTrid($s)
        {
            if(preg_match('/[^0-9a-zA-Z]/', $s)) {
                return false;
            }
            if(strlen($s)!=40) {
                return false;
            }
            return true;
        }

        function checkEmail($s)
        {
            if (!preg_match('/^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/', $s)) {
                $this->error('029', $this->__('Please enter the correct email.'));
            }
            return $s;
        }
        function checkSymbol($s)
        {
            if (preg_match('/[^0-9a-zA-Z,.]/', $s)) {
                $this->error('011', $this->__('Please enter the correct symbol.'));
            }
            return $s;
        }
        /**
         * Y/N 값 재설정.
         * 입력받은 값을 Y/N으로 만듧니다. 기본값이 N인데 $s의 값이 "N"이 아니면 "Y"값을 리턴
         * @param String $s 확인할 문자열
         * @param String $default 기본 값(Y or N).
         */
        function resetYN($s, $default)
        {
            $s = strtoupper($s);
            $default = strtoupper($default);
            return $s == $default ? $default : ($default=='N' ? 'Y' : 'N');
        }

        /**
         * 전화번호 형식 확인
         * 숫자,+,- 만으로 구성된 전화번호가 아니면 애러 처리.
         *
         * @param String $s 전화번호. 
         * 
         * @return String $s 입력받은 전화번호. 
         * 
         */
        function checkMobileNumber($s)
        {
            if($s && preg_match('/[^0-9\+\-]/', $s)) {
                $this->error('011', $GLOBALS['simplerestful']->displayParamName().$this->__('Please enter the correct mobile number.'));
            }
            return $s;
        }

        function checkCountryCode($s)
        {
            if($s && !preg_match('/^[A-Z]{2}$/', $s)) {
                $this->error('011', $GLOBALS['simplerestful']->displayParamName().$this->__('Please enter the correct country code.'));
            }
            return $s;
        }

    }
    $GLOBALS['simplerestful'] = new SimpleRestful;
    define('__LOADED_SIMPLERESTFUL__', true);

    // ----------------------------------------------------------------- //
    // util function

    if(!function_exists('xml_encode')) {
        function xml_encode($mixed, $domElement=null, $DOMDocument=null) {
            if (is_null($DOMDocument)) {
                $DOMDocument =new DOMDocument;
                $DOMDocument->formatOutput = true;
                xml_encode($mixed, $DOMDocument, $DOMDocument);
                return $DOMDocument->saveXML();
            }
            else {
                // To cope with embedded objects
                if (is_object($mixed)) {
                $mixed = get_object_vars($mixed);
                }
                if (is_array($mixed)) {
                    foreach ($mixed as $index => $mixedElement) {
                        if (is_int($index)) {
                            if ($index === 0) {
                                $node = $domElement;
                            }
                            else {
                                $node = $DOMDocument->createElement($domElement->tagName);
                                $domElement->parentNode->appendChild($node);
                            }
                        }
                        else {
                            $plural = $DOMDocument->createElement($index);
                            $domElement->appendChild($plural);
                            $node = $plural;
                            if (!(rtrim($index, 's') === $index)) {
                                $singular = $DOMDocument->createElement(rtrim($index, 's'));
                                $plural->appendChild($singular);
                                $node = $singular;
                            }
                        }

                        xml_encode($mixedElement, $node, $DOMDocument);
                    }
                }
                else {
                    $mixed = is_bool($mixed) ? ($mixed ? 'true' : 'false') : $mixed;
                    $domElement->appendChild($DOMDocument->createTextNode($mixed));
                }
            }
        }
    }

    /**
     * convert number to string
     * 10 * 0.000000000000000111 -> '10.000000000000000111'
     * 0.000000000000000111 -> '0.000000000000000111'
     * 111000000000000 * pow(10,18) -> "111000000000000000000000000000000"
     * 111000000000000 -> "111000000000000"
     * 1.0E-5 -> string(7) "0.00001"
     */
    if(!function_exists('numtostr')) {
        function numtostr($n) {
            $decimals = 0;
            $sign = '+';
            $s = strval($n);
            // 10승 확인.
            if(strpos($s, 'E')!==false) {
                $t = explode('E',$s);
                $number = $t[0];
                $decimals = substr($t[1],1);
                $sign = substr($t[1],0,1);
                // 소숫점 확인
                if(strpos($number, '.')!==false) {
                    $t = explode('.',$number);
                    $number = $t[0].$t[1];
                    if($sign=='+') {
                        $decimals -= strlen($t[1]);
                    } else {
                        $decimals -= strlen($t[0]);
                    }
                }
            } else {
                $number = $s;
            }
            if($sign=='+') {
                $s = $number . str_repeat('0',$decimals);
            } else {
                $d = str_repeat('0',$decimals).$number;
                $d = preg_replace('/0{1,}$/','',$d); // 끝0제거
                $s = '0.'.$d;
            }
            return $s;
        }
    }


    // ----------------------------------------------------------------- //
    // validator function

    function loadParam($s)
    {
        return $GLOBALS['simplerestful']->loadParam($s);
    }

    function setDefault($s, $val)
    {
        return $GLOBALS['simplerestful']->setDefault($s, $val);
    }

    function checkEmpty($s, $name='')
    {
        return $GLOBALS['simplerestful']->checkEmpty($s, $name);
    }

    function checkZero($s)
    {
        return $GLOBALS['simplerestful']->checkZero($s);
    }

    function checkNumber($s)
    {
        return $GLOBALS['simplerestful']->checkNumber($s);
    }
    function checkEmail($s)
    {
        return $GLOBALS['simplerestful']->checkEmail($s);
    }

    /**
     * validate api transaction id
     * @param String api transaction id
     * @return Boolean validate result. true: valid. false: invalid
     */
    function isTrid($s)
    {
        return $GLOBALS['simplerestful']->isTrid($s);
    }
    function checkSymbol($s)
    {
        return $GLOBALS['simplerestful']->checkSymbol($s);
    }
    function resetYN($s, $default)
    {
        return $GLOBALS['simplerestful']->resetYN($s, $default);
    }
    function checkMobileNumber($s)
    {
        return $GLOBALS['simplerestful']->checkMobileNumber($s);
    }
    function checkCountryCode($s)
    {
        return $GLOBALS['simplerestful']->checkCountryCode($s);
    }

    function __($s, $r=null)
    {
        return $GLOBALS['simplerestful']->__($s, $r);
    }

    function _e($s, $r=null)
    {
        return $GLOBALS['simplerestful']->_e($s, $r);
    }

}

/**
 * 실수용 number_format
 * @param Number $n 숫자값
 * @param Number $d 소수점 자릿수. 이하는 버림
 * @param String $cut_method 소수점 자릿수 이하 숫자 처리방법. 기본 버림.
 * @return String 콤마가 포함된 숫자형 문자
 */
if(!function_exists('real_number_format')) {
    function real_number_format($n, $d=0, $cut_method='floor') {
        if(!$d) $d = 8;
        $n = numtostr($n);
        $n = explode('.', $n.'');
        $n[0] = number_format($n[0]);
        if($n[1]) {
            $n[1] = preg_replace('/0+$/','',$n[1]); // 오른쪽 무의미한 0 제거
            $n[1] = '0.'.$n[1]; // 소숫점으로 변경
            $n[1] = $n[1] * pow(10, $d); // 자를 자릿수만큼 올리고
            switch($cut_method) {
                case 'round': $n[1] = round($n[1]); break; // 반올림 처리
                case 'ceil': $n[1] = ceil($n[1]); break; // 올림 처리
                case 'floor': default: $n[1] = floor($n[1]); break; // 버림 처리
            }
            $n[1] = $n[1] / pow(10, $d); // 자를 자릿수만큼 내림
            $n[1] = substr($n[1].'', 2); // 0. 제거 
            $n = $n[1] ? $n[0].'.'.$n[1] : $n[0];
        } else {
            $n = $n[0];
        }
        return $n;
    }
}
/**
 * 실수용 number
 * @param Number $n 숫자값
 * @param Number $d 소수점 자릿수. 이하는 버림
 * @param String $cut_method 소수점 자릿수 이하 숫자 처리방법. 기본 버림.
 * @return String 콤마가 제거된 숫자형 문자
 */
if(!function_exists('real_number')) {
    function real_number($n, $d=0, $cut_method='floor') {
        return str_replace(',', '', real_number_format($n, $d, $cut_method) );
    }
}