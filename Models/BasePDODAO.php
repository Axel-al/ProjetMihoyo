<?php
namespace Models;
use Config\Config;

abstract class BasePDODAO {
    private \PDO $db;

    private function getDB() : \PDO {
        return $this->db ??= (function() {
            $db = new \PDO(Config::get('dsn', required: true), Config::get('user', required: true), Config::get('pass', required: true));
            $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return $db;
        })();
    }

    protected function execRequest(string $sql, ?array $params = null, ?\PDOException &$error = null) : \PDOStatement|false {
        try {
            if ($params !== null) {
                preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $matches);

                $filteredParams = [];
                foreach ($matches[1] as $key)
                    if (array_key_exists($key, $params))
                        $filteredParams[$key] = $params[$key];

                $params = $filteredParams;
            }
            
            $stmt = $this->getDB()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (\PDOException $e) {
            if ($error !== null)
                $error = $e;

            return false;
        }
    }
}