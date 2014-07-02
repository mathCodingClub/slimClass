<?php

namespace WS;

use \WS\annotations as WSann;

abstract class service {
  // content types

  const CT_PLAIN = 0;
  const CT_JSON = 1;

  protected $app;
  protected $response;
  protected $request;
  protected $autoMapMethods;

  // $app = instance of \Slim\Slim()
  // $path = $path to service

  protected function __construct($app, $path, $autoMap = true) {
    // $app = \Slim\Slim::getInstance();
    $this->path = $path;
    $this->app = $app;
    $this->response = $app->response();
    $this->request = $app->request();
    $this->autoMapMethods = array('get', 'post', 'delete', 'options');

    if ($autoMap) {
      $class = new \ReflectionClass($this);
      $methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);
      $sortFun = function($value1, $value2) {
          return strlen($value1->name) > strlen($value2->name);
        };
      usort($methods, $sortFun);
      // go through all the public methods
      foreach ($methods as $method) {
        foreach ($this->autoMapMethods as $httpMethod) {
          if (strpos($method->name, $httpMethod) === 0) {
            $path = $this->getPathStr($method->name, $httpMethod) .
              $this->getParametersStr($method);
            // array_push($this->methods, $httpMethod . ': ' . $path);
            call_user_func(array($this->app, $httpMethod), $path, array($this, $method->name));
            break;
          }
        }
      }
    }
  }

  // path/api is automatically bind to this

  /**
   * @WSann\HelpTxt("Returns this api help command")
   */
  public function getApi() {
    $class = new \ReflectionClass($this);
    $methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);
    $api = array();
    foreach ($methods as $method) {
      foreach ($this->autoMapMethods as $httpMethod) {
        if (strpos($method->name, $httpMethod) === 0) {


          $p = $this->getPathStr($method->name, $httpMethod);
          $p = substr($p, 1);
          $p = str_replace('/', ' ', $p);
          $s = str_replace('(/', ' (', $this->getParametersStr($method));
          $s = str_replace('/', ' ', $s);
          $path = $p . $s;

          if ($httpMethod == 'get') {
            $txt = '!' . $path;
          } else {
            $txt = '!' . $httpMethod . ':' . $path;
          }
          $help = $this->getAnnotations($method->name, '\WS\annotations\HelpTxt');
          if (count($help) == 1){
            $txt .= ' // ' . $help[0]->get();
          }
          array_push($api, $txt);
          break;
        }
      }
    }

    $arsort = function($v1,$v2){
      return $v1 < $v2;
    };
    usort($api,$arsort);
    $this->setCT(self::CT_PLAIN);
    $this->response->body(implode(PHP_EOL, $api));
  }

  private function getAnnotations($name, $classInstance = null) {
    $reader = new \Doctrine\Common\Annotations\AnnotationReader();
    // register annotations

    $r = new \ReflectionMethod($this, $name);
    $methodAnnotations = $reader->getMethodAnnotations($r);
    if (is_null($classInstance)) {
      return $methodAnnotations;
    }

    $ar = array();

    foreach ($methodAnnotations as $key => $annotation) {
      if (strcmp(get_class($annotation), $classInstance)) {
        array_push($ar, $annotation);
      }
    }
    return $ar;
  }

  // protected

  protected function allowCrossPosting($allowFrom = null) {

    if (!isset($_SERVER['HTTP_ORIGIN'])) {
      return;
    }
    $from = $_SERVER['HTTP_ORIGIN'];
    if (!is_null($allowFrom)) {
      if (!is_array($allowFrom)) {
        $allowFrom = array($allowFrom);
      }
      $matches = false;
      foreach ($allowFrom as $test) {
        if (preg_match($test, $from)) {
          $matches = true;
          break;
        }
      }
      if (!$matches) {
        return;
      }
    }

    $this->response->headers->set('Content-Type', $from);
    $this->response->headers->set('Access-Control-Allow-Origin', $from);
    $this->response->headers->set('Access-Control-Allow-Headers', 'Content-Type');
    $this->response->headers->set('Access-Control-Allow-Methods', 'GET,HEAD,POST,OPTIONS,TRACE');
    $this->response->headers->set('Access-Control-Allow-Credentials', 'true');
    $this->response->headers->set('Allow', 'GET,HEAD,POST,OPTIONS,TRACE');
  }

  protected function getBodyAsJSON() {
    return json_decode($this->request->getBody(), true);
  }

  protected function sendError($e,$body='') {
    $this->setCT(self::CT_PLAIN);
    $this->response->status($e->getCode());
    $this->response->body($body . $e->getMessage() . PHP_EOL);
  }

  protected function setCT($type) {
    switch ($type) {
      case self::CT_PLAIN:
        $this->app->contentType('text/plain;charset=utf-8');
        return;
      case self::CT_JSON:
        $this->app->contentType('application/json;charset=utf-8');
        return;
    }
  }

  /*
   * Private
   */

  private function getParametersStr($method) {
    $path = '';
    $pathEnd = '';
    $reqPar = $method->getNumberOfRequiredParameters();
    foreach ($method->getParameters() as $key => $param) {
      if ($key < $reqPar) {
        $path .= '/:' . str_replace('_', '+', $param->name);
      } else {
        $path .= '(/:' . $param->name;
        $pathEnd .= ')';
      }
    }
    return $path . $pathEnd;
  }

  private function getPathStr($name, $keyWord) {
    $name = substr($name, strlen($keyWord));
    if (($pos = strpos($name, '_')) !== false) {
      $name = substr($name, 0, $pos);
    }
    $path = $this->path;
    while (strlen($name) > 0) {
      preg_match('@([A-Z][a-z].*?)([A-Z])@', $name, $temp);
      if (count($temp) == 0) {
        $path .= '/' . strtolower($name);
        break;
      } else {
        $path .= '/' . strtolower($temp[1]);
        $name = substr($name, strlen($temp[1]));
      }
    }
    return $path;
  }

}

?>
