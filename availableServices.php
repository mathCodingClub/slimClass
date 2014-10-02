<?php

namespace slimClass;

class availableServices {

  // this is static so that it is shared between all the services
  private static $services = array();

  public function addMethod($service, $path, $description, $httpMethod, $variables) {
    if (!isset(self::$services[$service])) {
      throw new \Exception('This service is not yet inited. Init service first.');
    }
    $ar = array();
    if (count($variables)) {
      foreach ($variables as $var) {
        $varData = array('name' => $var->name,
          'type' => $var->type,
          'description' => $var->desc,
          'hasDefaultValue' => $var->hasDefaultValue());
        if ($varData['hasDefaultValue']) {
          $varData['default'] = $var->default;
        }
        array_push($ar, $varData);
      }
    }

    array_push(self::$services[$service]['methods'], array('path' => $path,
      'httpMethod' => $httpMethod,
      'variables' => $ar,
      'description' => $description));
  }

  public function addService($service, $path, $description) {
    if (isset(self::$services[$service])) {
      return;
    }
    self::$services[$service] = array('description' => $description,
      'name' => $service,
      'path' => $path,
      'methods' => array());
  }

  public function getServiceAsHtml($service) {
    $ser = $this->getServices($service);
    $cont = "<h2>{$ser['name']}</h2>" .
      "<i>{$ser['description']}</i><p>" .
      "List of methods<ul>";
    foreach ($ser['methods'] as $method) {
      $cont .= "<li>Path: <b>{$method['path']}</b><br>" .
        "HTTP request method: <b>{$method['httpMethod']}</b><br>";
      if (count($method['variables'])) {
        foreach ($method['variables'] as $var) {
          $cont .= 'Variable: <b>' . $var['name'] . '</b> (' . $var['type'] . ') ';
          if ($var['hasDefaultValue']) {
            $cont .= '{' . $var['default'] . '} ';
          }
          $cont .= $var['description'] . '<br>';
        }
      }
      $cont .= "<i>{$method['description']}</i>";
    }
    $cont .= '</p>';
    return $cont;
  }

  public function getServiceAsJson($service) {
    return $this->getServices($service);
  }

  public function getServiceAsTxt($service) {
    $ser = $this->getServices($service);
    $cont = "==\n{$ser['name']}\n==\n" .
      "Description: {$ser['description']}\n" .
      "List of methods\n";
    foreach ($ser['methods'] as $method) {
      $cont .= "--\nDescription: {$method['description']}\nPath: {$method['path']}\n" .
        "HTTP request method: {$method['httpMethod']}\n";
      if (count($method['variables'])) {
        foreach ($method['variables'] as $var) {
          $cont .= 'Variable: ' . $var['name'] . ' (' . $var['type'] . ') ';
          if ($var['hasDefaultValue']) {
            $cont .= '{' . $var['default'] . '} ';
          }
          $cont .= "{$var['description']}\n";
        }
      }
    }
    return $cont . "--";
  }

  public function getServiceNameFromPath($serviceName){
    foreach (self::$services as $key => $service){
      if ('/' . $serviceName == $service['path']){
        return $key;
      }
    }
    return null;
  }

  public function getServices($service = null) {
    if (is_null($service)) {
      return self::$services;
    }
    return self::$services[$service];
  }

  public function getServicesAsTxt() {
    $this->sortServices();
    $cont = "DESCRIPTION OF THE MCC REST API\n============\n";
    foreach (self::$services as $service => $data) {
      $cont .= "\n" . $this->getServiceAsTxt($service);
      $cont .= "\n";
    }
    return $cont;
  }

  private function sortServices(){
    $sortFun = function($a,$b){
      return $a['name'] > $b['name'];
    };
    usort(self::$services,$sortFun);
  }

}

?>
