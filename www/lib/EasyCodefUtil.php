<?php

//namespace io\codef\api;

/*use Exception;
use phpseclib\Crypt\RSA;
use phpseclib\Math\BigInteger;
use phpseclib\File\X509;
*/

class EasyCodefUtil {

    /**
     * RSA 암호화
     *
     * @param string $plainText
     * @param string $publicKey
     * @return string
     * @throws Exception
     */
    public static function encryptRSA($plainText, $publicKey) {
        $rsa = new RSA();
        $rsa->setPublicKeyFormat('PKCS8');
        $rsa->loadKey($publicKey, RSA::PUBLIC_FORMAT_PKCS8);
        $rsa->setEncryptionMode(RSA::ENCRYPTION_PKCS1);
        $encrypted = $rsa->encrypt($plainText);
        $base64Encrypted = base64_encode($encrypted);
        return $base64Encrypted;
    }

    /**
     * 파일 정보를 BASE64 문자열로 인코딩
     *
     * @param string $filePath
     * @return string
     * @throws Exception
     */
    public static function encodeToFileString($filePath) {
        $fileContent = file_get_contents($filePath);
        $base64FileString = base64_encode($fileContent);
        return $base64FileString;
    }

    /**
     * 토큰 맵 변환
     *
     * @param string $token
     * @return array
     * @throws Exception
     */
    public static function getTokenMap($token) {
        $splitString = explode('.', $token);
        $base64EncodedBody = $splitString[1];
        $tokenBody = base64_decode($base64EncodedBody);
        $tokenMap = json_decode($tokenBody, true);
        return $tokenMap;
    }

    /**
     * 요청 토큰 정합성 체크
     *
     * @param int $expInt
     * @return bool
     */
    public static function checkValidity($expInt) {
        $now = time() * 1000;
        $expStr = $expInt . "000";
        $exp = (int) $expStr;
        if ($now > $exp || ($exp - $now < 3600000)) {
            return false;
        }
        return true;
    }
}
?>