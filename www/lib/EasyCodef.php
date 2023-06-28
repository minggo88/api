<?php

include dirname(__file__) . "/../../lib/EasyCodefProperties.php";
include dirname(__file__) . "/../../lib/EasyCodefServiceType.php";

class EasyCodef {
    private $mapper;
    private $properties;

    public function __construct() {
        $this->mapper = new \JsonMapper();
        $this->properties = new EasyCodefProperties();
    }

    public function setClientInfo($clientId, $clientSecret) {
        $this->properties->setClientInfo($clientId, $clientSecret);
    }

    public function setClientInfoForDemo($demoClientId, $demoClientSecret) {
        $this->properties->setClientInfoForDemo($demoClientId, $demoClientSecret);
    }

    public function setPublicKey($publicKey) {
        $this->properties->setPublicKey($publicKey);
    }

    public function getPublicKey() {
        return $this->properties->getPublicKey();
    }

    public function requestProduct($productUrl, $serviceType, $parameterMap) {
        $validationFlag = true;

        // #1.필수 항목 체크 - 클라이언트 정보
        $validationFlag = $this->checkClientInfo($serviceType->getServiceType());
        if (!$validationFlag) {
            $response = new EasyCodefResponse(EasyCodefMessageConstant::EMPTY_CLIENT_INFO);
            return $this->mapper->writeValueAsString($response);
        }

        // #2.필수 항목 체크 - 퍼블릭 키
        $validationFlag = $this->checkPublicKey();
        if (!$validationFlag) {
            $response = new EasyCodefResponse(EasyCodefMessageConstant::EMPTY_PUBLIC_KEY);
            return $this->mapper->writeValueAsString($response);
        }

        // #3.추가인증 키워드 체크
        $validationFlag = $this->checkTwoWayKeyword($parameterMap);
        if (!$validationFlag) {
            $response = new EasyCodefResponse(EasyCodefMessageConstant::INVALID_2WAY_KEYWORD);
            return $this->mapper->writeValueAsString($response);
        }

        // #4.상품 조회 요청
        $response = EasyCodefConnector::execute($productUrl, $serviceType->getServiceType(), $parameterMap, $this->properties);

        // #5.결과 반환
        return $this->mapper->writeValueAsString($response);
    }

    public function requestCertification($productUrl, $serviceType, $parameterMap) {
        $validationFlag = true;

        // #1.필수 항목 체크 - 클라이언트 정보
        $validationFlag = $this->checkClientInfo($serviceType->getServiceType());
        if (!$validationFlag) {
            $response = new EasyCodefResponse(EasyCodefMessageConstant::EMPTY_CLIENT_INFO);
            return $this->mapper->writeValueAsString($response);
        }

        // #2.필수 항목 체크 - 퍼블릭 키
        $validationFlag = $this->checkPublicKey();
        if (!$validationFlag) {
            $response = new EasyCodefResponse(EasyCodefMessageConstant::EMPTY_PUBLIC_KEY);
            return $this->mapper->writeValueAsString($response);
        }
        
        // #3.추가인증 파라미터 필수 입력 체크
        $validationFlag = $this->checkTwoWayInfo($parameterMap);
        if (!$validationFlag) {
            $response = new EasyCodefResponse(EasyCodefMessageConstant.INVALID_2WAY_INFO);
            return json_encode($response);
        }

        /** #4. 상품 조회 요청 */
        $response = EasyCodefConnector::execute($productUrl, $serviceType->getServiceType(), $parameterMap, $properties);

        /** #5. 결과 반환 */
        return json_encode($response);
    }

    /**
     * Desc : 서비스 타입에 따른 클라이언트 정보 설정 확인
     * @Company : ©CODEF corp.
     * @Author  : notfound404@codef.io
     * @Date    : Jun 26, 2020 3:33:23 PM
     * @param serviceType
     * @return
     */
    private function checkClientInfo($serviceType) {
        if ($serviceType == 0) {
            if (empty($properties->getClientId())) {
                return false;
            }
            if (empty($properties->getClientSecret())) {
                return false;
            }
        } elseif ($serviceType == 1) {
            if (empty($properties->getDemoClientId())) {
                return false;
            }
            if (empty($properties->getDemoClientSecret())) {
                return false;
            }
        } else {
            if (empty(EasyCodefConstant::SANDBOX_CLIENT_ID)) {
                return false;
            }
            if (empty(EasyCodefConstant::SANDBOX_CLIENT_SECRET)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Desc : 퍼블릭키 정보 설정 확인
     * @Company : ©CODEF corp.
     * @Author  : notfound404@codef.io
     * @Date    : Jun 26, 2020 3:33:31 PM
     * @return
     */
    private function checkPublicKey() {
        if (empty($properties->getPublicKey())) {
            return false;
        }
        return true;
    }

    /**
     * Desc : 추가인증 파라미터 설정 확인
     * @Company : ©CODEF corp.
     * @Author  : notfound404@codef.io
     * @Date    : Jun 26, 2020 3:33:39 PM
     * @param parameterMap
     * @return
     */
    private function checkTwoWayInfo($parameterMap) {
        if (!isset($parameterMap['is2Way']) || !is_bool($parameterMap['is2Way']) || !$parameterMap['is2Way']) {
            return false;
        }

        if (!isset($parameterMap['twoWayInfo'])) {
            return false;
        }

        $twoWayInfoMap = $parameterMap['twoWayInfo'];
        if (!isset($twoWayInfoMap['jobIndex']) || !isset($twoWayInfoMap['threadIndex']) || !isset($twoWayInfoMap['jti']) || !isset($twoWayInfoMap['twoWayTimestamp'])) {
            return false;
        }

        return true;
    }

    /**
     * Desc : 토큰 신규 발급 후 반환(코드에프 이용 중 추가 업무 사용을 하는 등 토큰 권한 변경이 필요하거나 신규 토큰이 필요한 경우시 사용)
     * @Company : ©CODEF corp.
     * @Author  : notfound404@codef.io
     * @Date    : Sep 16, 2020 11:58:32 AM
     * @param serviceType
     * @return
     * @throws JsonParseException
     * @throws JsonMappingException
     * @throws IOException
     * 
     */
public function requestNewToken($serviceType) {
    $clientId = null;
    $clientSecret = null;
    
    if ($serviceType->getServiceType() == 0) {
        $clientId = $properties->getClientId();
        $clientSecret = $properties->getClientSecret();
    } elseif ($serviceType->getServiceType() == 1) {
        $clientId = $properties->getDemoClientId();
        $clientSecret = $properties->getDemoClientSecret();
    } else {
        $clientId = EasyCodefConstant::SANDBOX_CLIENT_ID;
        $clientSecret = EasyCodefConstant::SANDBOX_CLIENT_SECRET;
    }
    
    $accessToken = null;
    $tokenMap = EasyCodefConnector::publishToken($clientId, $clientSecret); // 토큰 신규 발급
    if ($tokenMap != null) {
        $accessToken = $tokenMap["access_token"];
        EasyCodefTokenMap::setToken($clientId, $accessToken); // 발급 토큰 저장
        return $accessToken;
    } else {
        return null;
    }
}