<?php

//namespace io\codef\api;

//use \HashMap;
//use \Iterator;
//use \List;

class EasyCodefResponse extends HashMap {
    private $result;
    private $data;
    
    public function __construct() {
        $this->result = new HashMap();
        $this->data = new HashMap();
        
        $this->put(EasyCodefConstant::RESULT, $this->result);
        $this->put(EasyCodefConstant::DATA, $this->data);
        
        $this->setResultMessage(EasyCodefMessageConstant::OK->getCode(), EasyCodefMessageConstant::OK->getMessage(), "");
    }
    
    public function __construct_2(HashMap $map) {
        $iter = $map->keySet()->iterator();
        
        while ($iter->hasNext()) {
            $key = $iter->next();
            
            if (EasyCodefConstant::RESULT === $key) {
                $this->result = $map->get(EasyCodefConstant::RESULT);
                $this->put(EasyCodefConstant::RESULT, $this->result);
            } elseif (EasyCodefConstant::DATA === $key) {
                try {
                    $this->data = $map->get(EasyCodefConstant::DATA);
                } catch (ClassCastException $e) {
                    $this->data = $map->get(EasyCodefConstant::DATA);
                }
                $this->put(EasyCodefConstant::DATA, $this->data);
            } else {
                $this->put($key, $map->get($key));
            }
        }
    }
    
    public function __construct_3(EasyCodefMessageConstant $message) {
        $this->result = new HashMap();
        $this->data = new HashMap();
        
        $this->put(EasyCodefConstant::RESULT, $this->result);
        $this->put(EasyCodefConstant::DATA, $this->data);
        
        $this->setResultMessage($message->getCode(), $message->getMessage(), "");
    }
    
    public function __construct_4(EasyCodefMessageConstant $message, $extraMessage) {
        $this->result = new HashMap();
        $this->data = new HashMap();
        
        $this->put(EasyCodefConstant::RESULT, $this->result);
        $this->put(EasyCodefConstant::DATA, $this->data);
        
        $this->setResultMessage($message->getCode(), $message->getMessage(), $extraMessage);
    }
    
    protected function setResultMessage($errCode, $errMsg, $extraMsg) {
        $this->result->put(EasyCodefConstant::CODE, $errCode);
        $this->result->put(EasyCodefConstant::MESSAGE, $errMsg);
        $this->result->put(EasyCodefConstant::EXTRA_MESSAGE, $extraMsg);
    }
    
    protected function setResultMessage_2(EasyCodefMessageConstant $message) {
        $this->result->put(EasyCodefConstant::CODE, $message->getCode());
        $this->result->put(EasyCodefConstant::MESSAGE, $message->getMessage());
        $this->result->put(EasyCodefConstant::EXTRA_MESSAGE, $message->getExtraMessage());
    }
    
    public function __toString() {
        return "EasyCodefResponse [result=" . $this->result . ", data=" . $this->data . "]";
    }
}
?>