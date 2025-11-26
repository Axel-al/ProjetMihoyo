<?php
namespace Controllers\Router;

use Controllers\ElementController;
use Controllers\Router\Route\RouteLogs;
use League\Plates\Engine;
use Controllers\MainController;
use Controllers\PersoController;
use Controllers\Router\Route\RouteIndex;
use Controllers\Router\Route\RouteAddPerso;
use Controllers\Router\Route\RouteAddElement;
use Controllers\Router\Route\RouteDelPerso;
use Controllers\Router\Route\RouteEditPerso;
use Controllers\Router\Route\RouteLogin;
use Config\Paths;

class Router {
    private array $routeList;
    private array $ctrlList;
    private string $action_key;

    public function __construct(string $name_of_action_key = "action") {
        $this->action_key = $name_of_action_key;
        $this->createControllerList();
        $this->createRouteList();
    }

    private function createControllerList() : void {
        $mainController = new MainController(new Engine(Paths::projectRoot() . '/Views'));
        $this->ctrlList = [
            "main" => $mainController,
            "perso" => new PersoController(new Engine(Paths::projectRoot() . '/Views'), $mainController),
            "element" => new ElementController(new Engine(Paths::projectRoot() . '/Views'))
        ];
    }

    private function createRouteList() : void {
        $this->routeList = [
            "index" => new RouteIndex($this->ctrlList['main']),
            "add-perso" => new RouteAddPerso($this->ctrlList['perso']),
            "add-perso-element" => new RouteAddElement($this->ctrlList['element']),
            "logs" => new RouteLogs($this->ctrlList['main']),
            "del-perso" => new RouteDelPerso($this->ctrlList['perso']),
            "edit-perso" => new RouteEditPerso($this->ctrlList['perso']),
            "login" => new RouteLogin($this->ctrlList['main'])
        ];
    }

    public function routing(array $get, array $post) : void {
        $action = $get[$this->action_key] ?? 'index';
        $route = $this->routeList[$action] ?? $this->routeList['index'];
        
        $route->action($_SERVER['REQUEST_METHOD'] === 'POST' ? $post : $get, $_SERVER['REQUEST_METHOD']);
    }
}