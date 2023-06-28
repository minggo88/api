<?php

//namespace io\codef\api;

class EasyCodefServiceType {
    const SANDBOX = 2;
    const DEMO = 1;
    const API = 0;

    private $serviceType;

    private function __construct($serviceType) {
        $this->serviceType = $serviceType;
    }

    public function getServiceType() {
        return $this->serviceType;
    }

    public static function SANDBOX() {
        return new EasyCodefServiceType(self::SANDBOX);
    }

    public static function DEMO() {
        return new EasyCodefServiceType(self::DEMO);
    }

    public static function API() {
        return new EasyCodefServiceType(self::API);
    }
}
?>