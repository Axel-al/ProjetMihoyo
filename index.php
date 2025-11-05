<?php
require_once 'Helpers/Psr4AutoloaderClass.php';

$loader = new Helpers\Psr4AutoloaderClass();
$loader->register();
$loader->addNamespace('\Helpers', __DIR__ . '/Helpers');
$loader->addNamespace('\League\Plates', __DIR__ . '/Vendor/Plates/src');
$loader->addNamespace('\Controllers', __DIR__ . '/Controllers');

$templates = new \League\Plates\Engine(__DIR__ . '/Views');
$controller = new \Controllers\MainController($templates);
$controller->index();
