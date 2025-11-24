<?php
namespace Config;

use Exception;

class Config {
    private static ?array $param = null;

    // Renvoie la valeur d'un paramètre de configuration
    public static function get($nom, $valeurParDefaut = null, bool $required = false) {
        if (array_key_exists($nom, self::getParameter())) {
            return self::getParameter()[$nom];
        }
        
        if ($required) {
            throw new Exception("Missing required config key: " . $nom);
        }

        return $valeurParDefaut;
    }

    // Renvoie le tableau des paramètres en le chargeant au besoin
    private static function getParameter() {
        if (self::$param === null) {
            $cheminFichier = __DIR__ . "/prod.ini";
            if (!file_exists($cheminFichier)) {
                $cheminFichier = __DIR__ . "/dev.ini";
            }
            if (!file_exists($cheminFichier)) {
                throw new Exception("Aucun fichier de configuration trouvé");
            }
            else {
                self::$param = parse_ini_file($cheminFichier);
            }
        }
        return self::$param;
    }
}
