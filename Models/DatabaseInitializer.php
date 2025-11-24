<?php
namespace Models;

class DatabaseInitializer extends BasePDODAO {
    public function init() : void {
        if ($this->isTableEmpty('PERSONNAGE')) {
            $this->createPersonnageTable();
        }
        // if ($this->isTableEmpty('ORIGIN')) {
        //     $this->createOriginTable();
        // }
        // if ($this->isTableEmpty('UNITORIGIN')) {
        //     $this->createUnitOriginTable();
        // }
    }
    
    private function createPersonnageTable(bool $defaultPersonnages = true) : void {
        $test = $this->execRequest("
            CREATE TABLE IF NOT EXISTS PERSONNAGE (
                id VARCHAR(13) primary key,
                name VARCHAR(255) NOT NULL,
                element VARCHAR(255) NOT NULL,
                unitclass VARCHAR(255) NOT NULL,
                origin VARCHAR(255),
                rarity INT NOT NULL CHECK (rarity BETWEEN 4 AND 5),
                urlImg VARCHAR(255) NOT NULL,
                description TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
        if ($defaultPersonnages) {
            $data = json_decode(file_get_contents("data/genshin_characters.json"), true);
            foreach ($data as $personnage) {
                $this->execRequest("INSERT INTO PERSONNAGE (id, name, element, unitclass, origin, rarity, urlImg, description)
                    VALUES (:id, :name, :element, :unitclass, :origin, :rarity, :urlImg, :description);", array_merge(['id' => uniqid()], $personnage));
            }
        }
    }

    // private function createOriginTable(bool $defaultOrigins = true) : void {
    //     $this->execRequest("
    //         CREATE TABLE IF NOT EXISTS ORIGIN (
    //             id INT AUTO_INCREMENT PRIMARY KEY,
    //             name VARCHAR(255) NOT NULL,
    //             url_img VARCHAR(255) UNIQUE NOT NULL
    //         ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
    //     ");
    //     if ($defaultOrigins) {
    //         $data = json_decode(file_get_contents("data/origins_info.json"), true);
    //         foreach ($data as $origin) {
    //             $this->execRequest("INSERT INTO ORIGIN (name, url_img) VALUES (:name, :url_img);",
    //                 ['name' => $origin['name'], 'url_img' => $origin['url_img']]);
    //         }
    //     }
    // }

    // private function createUnitOriginTable(bool $defaultUnitOrigins = true) : void {
    //     $this->execRequest("
    //         CREATE TABLE IF NOT EXISTS UNITORIGIN (
    //             id INT AUTO_INCREMENT PRIMARY KEY,
    //             id_unit VARCHAR(13) NOT NULL,
    //             id_origin INT NOT NULL,
    //             FOREIGN KEY (id_unit) REFERENCES UNIT(id) ON DELETE CASCADE ON UPDATE CASCADE,
    //             FOREIGN KEY (id_origin) REFERENCES ORIGIN(id) ON DELETE CASCADE ON UPDATE CASCADE
    //         ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
    //     ");
    //     if ($defaultUnitOrigins) {
    //         $data = json_decode(file_get_contents("data/champs_info.json"), true);
    //         foreach ($data as $unit) {
    //             $row = $this->execRequest("SELECT id FROM UNIT WHERE name = :name;", ['name' => $unit['name']])->fetch(\PDO::FETCH_ASSOC);
    //             if (!$row) {
    //                 continue;
    //             }
    //             $idUnit = $row['id'];
    //             foreach ($unit['origins'] as $origin) {
    //                 $row = $this->execRequest("SELECT id FROM ORIGIN WHERE name = :name;", ['name' => $origin])->fetch(\PDO::FETCH_ASSOC);
    //                 if (!$row) {
    //                     continue;
    //                 }
    //                 $idOrigin = $row['id'];
    //                 $this->execRequest("INSERT INTO UNITORIGIN (id_unit, id_origin) VALUES (:id_unit, :id_origin);",
    //                     ['id_unit' => $idUnit, 'id_origin' => $idOrigin]);
    //             }
    //         }
    //     }
    // }

    private function isTableEmpty(string $tableName) : bool {
        $stmt = $this->execRequest("SELECT COUNT(*) FROM $tableName;");
        if ($stmt === false) {
            return true;
        }
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result['COUNT(*)'] === 0;
    }
}