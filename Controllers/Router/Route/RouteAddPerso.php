<?php
namespace Controllers\Router\Route;

use Controllers\Router\Route;
use Controllers\PersoController;

class RouteAddPerso extends Route {
    private PersoController $controller;

    public function __construct(PersoController $controller) {
        $this->controller = $controller;
    }

    public function get(array $params = []) : void {
        $this->controller->displayAddPerso();
    }

    public function post(array $params = []) : void {
        try {
            $data = [
                "name" => $this->getParam($params, "name", false),
                "element" => $this->getParam($params, "element", false),
                "unitclass" => $this->getParam($params, "unitclass", false),
                "rarity" => (int)$this->getParam($params, "rarity", false),
                "origin" => $this->getParam($params, "origin") ?: null,
                "urlImg" => $this->getParam($params, "urlImg", false),
                "description" => $this->getParam($params, "description") ?: null
            ];
            $this->controller->addPerso($data);
        } catch (\Exception $e) {
            $this->controller->displayAddPerso($e->getMessage());
        }
    }
}