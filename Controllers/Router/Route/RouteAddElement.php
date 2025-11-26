<?php
namespace Controllers\Router\Route;

use Controllers\Router\Route;
use Controllers\ElementController;

class RouteAddElement extends Route {
    private ElementController $controller;

    public function __construct(ElementController $controller) {
        $this->controller = $controller;
    }

    public function get(array $params = []) : void {
        $this->controller->displayAddElement();
    }

    public function post(array $params = []) : void {
        
    }
}