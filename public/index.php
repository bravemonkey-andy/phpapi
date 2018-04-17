<?php
require __DIR__.'/../app/autoload.php';

$app = new app\Application(realpath(__DIR__.'/../'));

$app->run($app);