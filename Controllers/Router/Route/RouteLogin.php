<?php
namespace Controllers\Router\Route;

use Controllers\Router\Route;
use Controllers\MainController;

class RouteLogin extends Route {
    private MainController $controller;

    public function __construct(MainController $controller) {
        $this->controller = $controller;
    }

    public function get(array $params = []) : void {
        $this->controller->displayLogin();
    }

    public function post(array $params = []) : void {
        $this->controller->displayLogin();
    }
}