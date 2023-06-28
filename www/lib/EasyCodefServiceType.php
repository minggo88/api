<?php
/**
 * Class EasyCodefServiceType
 *
 * Desc : CODEF 서비스 타입 enum 클래스
 * @Company : ©CODEF corp.
 * @Author  : notfound404@codef.io
 * @Date    : Jun 26, 2020 3:40:36 PM
 */
class EasyCodefServiceType {
    const SANDBOX = 2;
    const DEMO = 1;
    const API = 0;
    
    private $serviceType;
    
    private function __construct($serviceType) {
        $this->serviceType = $serviceType;
    }
    
    protected function getServiceType() {
        return $this->serviceType;
    }
}
?>