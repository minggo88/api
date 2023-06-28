<?php

namespace io\codef\api;

class EasyCodefTokenMap {
    /** 쉬운 코드에프 이용을 위한 토큰 저장 맵 */
    private static $ACCESS_TOKEN_MAP = array();

    /**
     * 토큰 저장
     * @param string $clientId
     * @param string $accessToken
     */
    public static function setToken($clientId, $accessToken) {
        self::$ACCESS_TOKEN_MAP[$clientId] = $accessToken;
    }

    /**
     * 토큰 반환
     * @param string $clientId
     * @return string|null
     */
    public static function getToken($clientId) {
        return isset(self::$ACCESS_TOKEN_MAP[$clientId]) ? self::$ACCESS_TOKEN_MAP[$clientId] : null;
    }
}
?>