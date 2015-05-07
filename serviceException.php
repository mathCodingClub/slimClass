<?php

namespace slimClass;

class serviceException extends \Exception {

  private $params;
  
  public function __construct($message, $code = 0, $params = array(), \Exception $previous = null) {
    parent::__construct($message, $code, $previous);
    $this->params = $params;
  }
  
  public function getParams(){
    return $this->params;
  }

}
