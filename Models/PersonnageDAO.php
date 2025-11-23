<?php
namespace Models;

class PersonnageDAO extends BasePDODAO {
    public function __construct() {
        (new DatabaseInitializer())->init();
    }

    public function getAll() : array {
        $stmt = $this->execRequest("SELECT * FROM PERSONNAGE ORDER BY name;");
        if ($stmt === false) {
            return [];
        }
        return $stmt->fetchAll();
    }

    public function getByID(string $id) : array {
        $stmt = $this->execRequest("SELECT * FROM PERSONNAGE WHERE id = :id;", ['id' => $id]);
        if ($stmt === false || $stmt->rowCount() === 0) {
            return [];
        }
        return $stmt->fetch();
    }
}