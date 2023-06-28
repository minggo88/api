<?php

/**
 * Class EasyCodefMessageConstant
 *
 * Desc : EasyCodef에서 사용되는 메시지 코드 클래스
 * @Company : ©CODEF corp.
 * @Author  : notfound404@codef.io
 * @Date    : Jun 26, 2020 3:36:41 PM
 */
class EasyCodefMessageConstant {
    const OK = "CF-00000";
    const INVALID_JSON = "CF-00002";
    const INVALID_PARAMETER = "CF-00007";
    const UNSUPPORTED_ENCODING = "CF-00009";
    const EMPTY_CLIENT_INFO = "CF-00014";
    const EMPTY_PUBLIC_KEY = "CF-00015";
    const INVALID_2WAY_INFO = "CF-03003";
    const INVALID_2WAY_KEYWORD = "CF-03004";
    const BAD_REQUEST = "CF-00400";
    const UNAUTHORIZED = "CF-00401";
    const FORBIDDEN = "CF-00403";
    const NOT_FOUND = "CF-00404";
    const METHOD_NOT_ALLOWED = "CF-00405";
    const LIBRARY_SENDER_ERROR = "CF-09980";
    const SERVER_ERROR = "CF-09999";
    
    private $code;
    private $message;
    private $extraMessage;
    
    private function __construct($code, $message) {
        $this->code = $code;
        $this->message = $message;
    }
    
    protected function getCode() {
        return $this->code;
    }
    
    protected function getMessage() {
        return $this->message;
    }
    
    protected function setExtraMessage($extraMessage) {
        $this->extraMessage = $extraMessage;
    }
    
    protected function getExtraMessage() {
        return $this->extraMessage;
    }
}
?>