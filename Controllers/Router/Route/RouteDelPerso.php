<?php
namespace Controllers\Router\Route;

use Controllers\Router\Route;
use Controllers\PersoController;

class RouteDelPerso extends Route {
    private PersoController $controller;

    public function __construct(PersoController $controller) {
        $this->controller = $controller;
    }

    public function get(array $params = []) : void {
        try {
            $idPerso = $this->getParam( $params, 'id', false);
            $this->controller->deletePersoAndIndex($idPerso);
        } catch (\Exception $e) {
            $this->controller->deletePersoAndIndex(message: $e->getMessage());
        }
    }

    public function post(array $params = []) : void {
        $this->controller->deletePersoAndIndex(message: "Erreur : La suppression doit être effectuée via une requête GET.");
    }
}