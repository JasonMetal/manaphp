<?php
//require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../../../ManaPHP/Loader.php';

$loader = new \ManaPHP\Loader();
require dirname(__DIR__) . '/app/Application.php';
$app = new \App\Admin\Application($loader);
$app->main();