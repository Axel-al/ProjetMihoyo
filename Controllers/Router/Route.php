<?php
namespace Controllers\Router;

abstract class Route {
    public function action(array $params = [], $method = 'GET') : void {
        try {
            $this->{strtolower($method)}($params);
        } catch(\BadMethodCallException $e) {
            throw new \Exception("Erreur : Méthode '$method': " . $method . "inconnue.");
        }
    }

    protected function getParam(array $array, string $paramName, bool $canBeEmpty = true) : string {
        if (isset($array[$paramName])) {
            if(!$canBeEmpty && empty($array[$paramName]))
                throw new \Exception("Erreur : Paramètre '$paramName' vide.");
            return $array[$paramName];
        } else
            throw new \Exception("Erreur : Paramètre '$paramName' manquant.");
    }

    abstract public function get(array $params = []) : void;

    abstract public function post(array $params = []) : void;
}