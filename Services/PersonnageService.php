<?php
namespace Services;

use Models\Personnage;
use Helpers\ViewStyle;

class PersonnageService {
    public static function validatePersonnage(Personnage $personnage): void {
        $errors = [];

        if (!$personnage->isInitialized("name") || empty($personnage->getName())) {
            $errors[] = "The name of the character cannot be empty.";
        }

        if (!$personnage->isInitialized("element") || empty($personnage->getElement())) {
            $errors[] = "The element of the character cannot be empty.";
        } else {
            $validElements = ["anemo", "geo", "electro", "dendro", "hydro", "pyro", "cryo", "adaptatif"];
            if (!in_array(ViewStyle::normalizeKey($personnage->getElement()), $validElements)) {
                $errors[] = "The element of the character is invalid.";
            }
        }

        if (!$personnage->isInitialized("unitclass") || empty($personnage->getUnitclass())) {
            $errors[] = "The class of the character cannot be empty.";
        } else {
            $validClasses = ["epee a une main", "epee a deux mains", "arme d'hast", "catalyseur","arc"];
            if (!in_array(ViewStyle::normalizeKey($personnage->getUnitclass()), $validClasses)) {
                $errors[] = "The class of the character is invalid.";
            }
        }

        if (!$personnage->isInitialized("rarity")) {
            $errors[] = "The rarity of the character must be set.";
        } elseif ($personnage->getRarity() < 4 || $personnage->getRarity() > 5) {
            $errors[] = "The rarity of the character must be between 4 and 5.";
        }

        $validOrigins = [null, "mondstadt", "liyue", "inazuma", "sumeru", "fontaine", "natlan", "nod-krai", "snezhnaya"];
        if (!in_array(ViewStyle::normalizeKey($personnage->getOrigin()), $validOrigins)) {
            $errors[] = "The origin of the character is invalid.";
        }

        if (!$personnage->isInitialized("urlImg") || empty($personnage->getUrlImg())) {
            $errors[] = "The image URL cannot be empty.";
        }

        if (!empty($errors)) {
            throw new \Exception("Erreur(s) : " . implode(" ; ", $errors));
        }
    }

    public static function fromRawToPersonnage(array $data): Personnage {
        $personnage = new Personnage();
        $personnage->hydrate($data);
        self::validatePersonnage($personnage);
        return $personnage;
    }

    public static function fromRawToListPersonnages(array $dataArray): array {
        $personnages = [];
        foreach ($dataArray as $data) {
            $personnages[] = self::fromRawToPersonnage($data);
        }
        return $personnages;
    }
}