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

    public function createPersonnage(Personnage $personnage) : void {
        $error = new \PDOException("Erreur lors de la création du personnage ayant l'ID " . $personnage->getId() . ".");
        $stmt = $this->execRequest("INSERT INTO PERSONNAGE (id, name, element, unitclass, origin, rarity, urlImg, description)
            VALUES (:id, :name, :element, :unitclass, :origin, :rarity, :urlImg, :description);", [
                'id' => $personnage->getId(),
                'name' => $personnage->getName(),
                'element' => $personnage->getElement(),
                'unitclass' => $personnage->getUnitclass(),
                'origin' => $personnage->getOrigin(),
                'rarity' => $personnage->getRarity(),
                'urlImg' => $personnage->getUrlImg(),
                'description' => $personnage->getDescription()
            ]
        , $error);

        if ($stmt === false)
            throw $error;
    }

    public function deletePerso(string $idPerso = '-1') : int {
        $error = new \PDOException("Erreur lors de la suppression du personnage avec l'ID $idPerso.");
        $stmt = $this->execRequest("DELETE FROM PERSONNAGE WHERE id = :id;", ['id' => $idPerso], $error);
        if ($stmt === false) {
            throw $error;
        }
        return $stmt->rowCount();
    }

    public function updatePerso(Personnage $personnage) : bool {
        $error = new \PDOException("Erreur lors de la mise à jour du personnage ayant l'ID " . $personnage->getId() . ".");
        $stmt = $this->execRequest("UPDATE PERSONNAGE SET
            name = :name,
            element = :element,
            unitclass = :unitclass,
            origin = :origin,
            rarity = :rarity,
            urlImg = :urlImg,
            description = :description
            WHERE id = :id;", [
                'id' => $personnage->getId(),
                'name' => $personnage->getName(),
                'element' => $personnage->getElement(),
                'unitclass' => $personnage->getUnitclass(),
                'origin' => $personnage->getOrigin(),
                'rarity' => $personnage->getRarity(),
                'urlImg' => $personnage->getUrlImg(),
                'description' => $personnage->getDescription()
            ]
        , $error);

        if ($stmt === false) {
            throw $error;
        }
        return $stmt->rowCount() > 0;
    }
}