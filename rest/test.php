<?php

namespace WS;

use \serviceAnnotations as sa;

class demo extends \slimClass\service {

  public function __construct($app, $path) {
    parent::__construct($app, $path);
    $this->setCT(self::CT_PLAIN);
  }

  // auto map to /me with optional input i.e. /me(/:string)
  public function getMe($string = 'default') {
    $this->response->body($string);
  }

  // auto map to /something
  public function getSomething() {
    $this->response->body('I will print something');
  }

  // custom map, notice parameter in between
  /**
   * @sa\route("/foo/:text/bar");
   */  
  public function getViaPath($text) {
    $this->response->body($text);
  }

}

?>
