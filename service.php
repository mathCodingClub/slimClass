<?php

namespace slimClass;

// define new use for service annotations
use \serviceAnnotations as ann;

abstract class service {

  // supported content types

  const CT_PLAIN = 0;
  const CT_JSON = 1;
  const CT_HTML = 2;

  protected $app;
  protected $autoMapMethods = null;
  protected $path;
  protected $response;
  protected $request;
  protected $availableServices = null;
  protected $useHelp = false;
  protected $serviceName;
  private $reader;

  // $app = instance of \Slim\Slim()
  // $path = $path to service
  public function __construct(\Slim\Slim $app, $path, $autoMap = true, $bindHelpTo = null) {
    $this->path = $path;
    $this->app = $app;
    $this->response = $app->response();
    $this->request = $app->request();
    $this->routes = array();
    $this->reader = new \Doctrine\Common\Annotations\AnnotationReader();

    // automap these methods, can be overridden in constructor
    if (is_null($this->autoMapMethods)) {
      $this->autoMapMethods = array('get', 'post', 'delete', 'options', 'put');
    }

    if (!is_null($bindHelpTo)) {
      $this->bindApiHelp($bindHelpTo);
    }

    if ($autoMap) {
      // map public methods to REST api
      $class = new \ReflectionClass($this);
      // get all public methods
      $methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);
      $sortFun = function($value1, $value2) {
        return strlen($value1->name) > strlen($value2->name);
      };
      // this sorting allows to map paths which have similar beginning but different end correctly.
      // Use longer function name for longer paths with the same beginning (after _ is ignored in path).
      usort($methods, $sortFun);
      $regex = '$(^' . implode($this->autoMapMethods, '|^') . ')$';
      foreach ($methods as $method) {
        preg_match($regex, $method->name, $temp);
        if (count($temp) == 0) {
          continue;
        }
        $httpMethod = $temp[0];
        // first check if this has custom route (remember httpmethod needs to be the first word)
        $ann = $this->reader->getMethodAnnotation($method, '\serviceAnnotations\route');
        if (is_object($ann)) {
          $path = $ann->value;
        } else {
          $path = $this->getPathStr($method->name, $httpMethod) .
                  $this->getParametersStr($method);
        }
        // remember in via method name is uppercase
        array_push($this->routes, $httpMethod . ':' . $path);
        if (strlen($this->path) == 1 && strlen($path) > 0) {
          $p = $path;
        } else {
          $p = $this->path . $path;
        }
        // add middleware here
        
        
        
        $this->app->map($p, array($this, $method->name))->
                via(strtoupper($httpMethod))->
                name(uniqid());
        $this->addToAvailableServices($httpMethod, $p, $method->name);
      }
      // if plain get does not exist, make it
      if (array_search('get:', $this->routes) === false && is_null($bindHelpTo)) {
        $this->bindApiHelp();
      }
    }
  }

  /**
   * @ann\routeDescription("Return info of this API.")
   */
  public function defaultGet() {
    if (!is_null($this->availableServices)) {
      switch ($this->accepts()) {
        case self::CT_HTML:
          $this->setCT(self::CT_HTML);
          $this->response->body($this->availableServices->getServiceAsHtml($this->serviceName));
          return;
        case self::CT_PLAIN:
          $this->setCT(self::CT_PLAIN);
          $this->response->body($this->availableServices->getServiceAsTxt($this->serviceName));
      }
    }
  }

  // protected

  protected function accepts() {
    $type = explode(',', $this->request->headers->get('Accept'));
    switch ($type[0]) {
      case 'text/html':
        return self::CT_HTML;
      case 'application/json':
        return self::CT_JSON;
      case 'text/plain':
        return self::CT_PLAIN;
      default:
        return self::CT_PLAIN;
    }
  }

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

  protected function bindApiHelp($path = '', $functionName = 'defaultGet') {
    $this->app->map($this->path . $path, array($this, $functionName))->
            via('GET')->
            name(uniqid());
    $this->addToAvailableServices('get', $this->path . $path, $functionName);
  }

  protected function getBodyAsJSON() {
    return json_decode($this->request->getBody(), true);
  }

  protected function sendError($e, $body = '') {
    $this->setCT(self::CT_PLAIN);
    $this->response->status($e->getCode());
    $this->response->body($body . $e->getMessage() . PHP_EOL);
  }

  protected function sendArrayAsJSON($array) {
    $this->setCT(self::CT_JSON);
    $this->response->body(json_encode($array, JSON_NUMERIC_CHECK));
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

  protected function addToAvailableServices($httpMethod, $path, $fName) {
    if (is_null($this->availableServices)) {
      $this->availableServices = new availableServices();
      $reader = new \Doctrine\Common\Annotations\AnnotationReader();
      $r = new \ReflectionClass($this);
      $serviceNameAnnotation = $reader->getClassAnnotation($r, 'WS\annotations\serviceName');
      if (!count($serviceNameAnnotation)) {
        return;
      }
      $serviceName = $serviceNameAnnotation->value;
      $this->serviceName = $serviceName;
      $serviceDescAnnotation = $reader->getClassAnnotation($r, 'WS\annotations\serviceDescription');
      $serviceDescription = '';
      if (count($serviceDescAnnotation)) {
        $serviceDescription = $serviceDescAnnotation->value;
      }
      $this->availableServices->addService($serviceName, $this->path, $serviceDescription);
      $this->useHelp = true;
    }
    if (!$this->useHelp) {
      return;
    }
    // register methods if they have descriptions
    $ann = $this->getMethodDescription($fName);
    if (!is_null($ann)) {
      $variables = $this->getMethodVariables($fName);
      $this->availableServices->addMethod($this->serviceName, $path, $ann, $httpMethod, $variables);
    }
  }

  private function getMethodDescription($fName) {
    $reader = new \Doctrine\Common\Annotations\AnnotationReader();
    $rm = new \ReflectionMethod($this, $fName);
    $methodAnnotation = $reader->getMethodAnnotation($rm, 'WS\annotations\routeDescription');
    if (count($methodAnnotation) > 0) {
      return $methodAnnotation->value;
    }
    return null;
  }

  private function getMethodVariables($fName) {
    $reader = new \Doctrine\Common\Annotations\AnnotationReader();
    $rm = new \ReflectionMethod($this, $fName);
    $methodAnnotation = $reader->getMethodAnnotations($rm);
    $typeName = 'WS\annotations\routeVariable';
    $ar = array();
    foreach ($methodAnnotation as $ann) {
      if ($ann instanceof $typeName) {
        array_push($ar, $ann);
      }
    }
    return $ar;
  }

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
    if (substr($name, 0, strlen($keyWord)) == $keyWord) {
      $name = substr($name, strlen($keyWord));
    }
    if (($pos = strpos($name, '_')) !== false) {
      $name = substr($name, 0, $pos);
    }
    $path = '';
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
