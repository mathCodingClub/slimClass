<?php

require_once '../vendor/mathCodingClub/serviceAnnotations/all.php';

require_once '../vendor/autoload.php';

require_once '../service.php';

require_once 'test.php';

$app = new \Slim\Slim();

new \WS\demo($app, '/demo');

$app->run();