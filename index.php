<?php
require_once __DIR__ . '/Helpers/Psr4AutoloaderClass.php';

$loader = new Helpers\Psr4AutoloaderClass();
$loader->register();
$loader->addNamespace('Helpers', __DIR__ . '/Helpers');
$loader->addNamespace('League\Plates', __DIR__ . '/Vendor/Plates/src');
$loader->addNamespace('Controllers', __DIR__ . '/Controllers');
$loader->addNamespace('Config', __DIR__ . '/Config');
$loader->addNamespace('Models', __DIR__ . '/Models');
$loader->addNamespace('Services', __DIR__ . '/Services');

$router = new Controllers\Router\Router();
$router->routing($_GET, $_POST);
