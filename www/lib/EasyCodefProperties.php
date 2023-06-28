<?php

/**
 * Desc : 코드에프의 쉬운 사용을 위한 프로퍼티 클래스 
 * @Company : ©CODEF corp.
 * @Author  : notfound404@codef.io
 * @Date    : Jun 26, 2020 3:36:51 PM
 */
class EasyCodefProperties {
    
    // 데모 엑세스 토큰 발급을 위한 클라이언트 아이디
    private $demoClientId = "";
    
    // 데모 엑세스 토큰 발급을 위한 클라이언트 시크릿
    private $demoClientSecret = "";    
    
    // OAUTH2.0 데모 토큰
    private $demoAccessToken = "";
    
    // 정식 엑세스 토큰 발급을 위한 클라이언트 아이디
    private $clientId = "";
    
    // 정식 엑세스 토큰 발급을 위한 클라이언트 시크릿
    private $clientSecret = "";    
    
    // OAUTH2.0 토큰
    private $accessToken = "";
    
    // RSA암호화를 위한 퍼블릭키
    private $publicKey = "";
    
    /**
     * Desc : 정식서버 사용을 위한 클라이언트 정보 설정
     * @Company : ©CODEF corp.
     * @Author  : notfound404@codef.io
     * @Date    : Jun 26, 2020 3:37:02 PM
     * @param clientId
     * @param clientSecret
     */
    public function setClientInfo($clientId, $clientSecret) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }
    
    /**
     * Desc : 데모서버 사용을 위한 클라이언트 정보 설정
     * @Company : ©CODEF corp.
     * @Author  : notfound404@codef.io
     * @Date    : Jun 26, 2020 3:37:10 PM
     * @param demoClientId
     * @param demoClientSecret
     */
    public function setClientInfoForDemo($demoClientId, $demoClientSecret) {
        $this->demoClientId = $demoClientId;
        $this->demoClientSecret = $demoClientSecret;
    }
    
    /**
     * Desc : 데모 클라이언트 아이디 반환
     * @Company : ©CODEF corp.
     * @Author  : notfound404@codef.io
     * @Date    : Jun 26, 2020 3:37:17 PM
     * @return
     */
    public function getDemoClientId() {
        return $this->demoClientId;
    }
    
    /**
     * Desc : 데모 클라이언트 시크릿 반환
     * @Company : ©CODEF corp.
     * @Author  : notfound404@codef.io
     * @Date    : Jun 26, 2020 3:37:23 PM
     * @return
     */
    public function getDemoClientSecret() {
        return $this->demoClientSecret;
    }
    
    /**
     * Desc : 데모 접속 토큰 반환
     * @Company : ©CODEF corp.
     * @Author  : notfound404@codef.io
     * @Date    : Jun 26, 2020 3:37:30 PM
     * @return
     */
    public function getDemoAccessToken() {
        return $this->demoAccessToken;
    }
    
    /**
     * Desc : 데모 클라이언트 시크릿 반환
     * @Company : ©CODEF corp.
     * @Author  : notfound404@codef.io
     * @Date    : Jun 26, 2020 3:37:36 PM
     * @Version : 1.0.1
     * @return
     */
    public function getClientId() {
        return $this->clientId;
    }
    
    /**
     * Desc : API 클라이언트 시크릿 반환
     * @Company : ©CODEF corp.
     * @Author  : notfound404@codef.io
     * @Date    : Jun 26, 2020 3:37:44 PM
     * @return
     */
    public function getClientSecret() {
        return $this->clientSecret;
    }
    
    /**
     * Desc : API 접속 토큰 반환
     * @Company : ©CODEF corp.
     * @Author  : notfound404@codef.io
     * @Date    : Jun 26, 2020 3:37:50 PM
     * @Version : 1.0.1
     * @return
     */
    public function getAccessToken() {
        return $this->accessToken;
    }
    
    /**
     * Desc : RSA암호화를 위한 퍼블릭키 반환
     * @Company : ©CODEF corp.
     * @Author  : notfound404@codef.io
     * @Date    : Jun 26, 2020 3:37:59 PM
     * @return
     */
    public function getPublicKey() {
        return $this->publicKey;
    }
    
    /**
     * Desc : RSA암호화를 위한 퍼블릭키 설정
     * @Company : ©CODEF corp.
     * @Author  : notfound404@codef.io
     * @Date    : Jun 26, 2020 3:38:07 PM
     * @Version : 1.0.1
     * @param publicKey
     */
    public function setPublicKey($publicKey) {
        $this->publicKey = $publicKey;
    }
    
    /**
     * Desc : 데모 접속 토큰 설정
     * @Company : ©CODEF corp.
     * @Author  : notfound404@codef.io
     * @Date    : Jun 26, 2020 3:38:14 PM
     * @param demoAccessToken
     */
    public function setDemoAccessToken($demoAccessToken) {
        $this->demoAccessToken = $demoAccessToken;
    }

    /**
     * Desc : API 접속 토큰 설정
     * @Company : ©CODEF corp.
     * @Author  : notfound404@codef.io
     * @Date    : Jun 26, 2020 3:38:21 PM
     * @param accessToken
     */
    public function setAccessToken($accessToken) {
        $this->accessToken = $accessToken;
    }
    
}