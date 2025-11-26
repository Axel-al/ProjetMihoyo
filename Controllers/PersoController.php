<?php
namespace Controllers;

use League\Plates\Engine;
use Models\Personnage;
use Models\PersonnageDAO;
use Services\PersonnageService;

class PersoController {
    private Engine $templates;
    private MainController $mainController;
    private PersonnageDAO $personnageDAO;
    
    public function __construct(Engine $templates, ?MainController $mainController = null) {
        $this->templates = $templates;
        $this->mainController = $mainController ?? (new MainController($templates));
        $this->personnageDAO = new PersonnageDAO();
    }

    public function displayAddPerso(?string $message = null): void {
        $this->replaceUrlByAddPerso();
        echo $this->templates->render('add-perso',
            [
                'page' => 'Ajouter un personnage',
                'gameName' => 'Genshin Impact',
                'message' => $message
            ]
        );
    }

    private function replaceUrlByAddPerso(): void {
        ?><script>
            history.replaceState(null, "", "<?= htmlspecialchars(strtok($_SERVER["REQUEST_URI"], '?'))
                . '?action=add-perso' ?>");
        </script><?php
    }

    public function addPerso(array $data = []): void {
        try {
            if (empty($data))
                throw new \Exception("Erreur : Données de personnage non fournies pour l'ajout.");

            $data['id'] = uniqid();
            $p = PersonnageService::fromRawToPersonnage($data);
            $this->personnageDAO->createPersonnage($p);
            $this->mainController->index("Personnage '" . $p->getName() . "' ajouté avec succès !");
        } catch (\Exception $e) {
            $this->displayAddPerso($e->getMessage());
        }
    }

    public function deletePersoAndIndex(?string $id = null, ?string $message = null): void {
        try {
            if ($id === null) {
                $message ??= "Erreur : ID de personnage non fourni pour la suppression.";
                throw new \Exception($message);
            }
            $p = $this->personnageDAO->getByID($id);
            if (empty($p))
                throw new \Exception("Erreur : Personnage avec l'ID $id non trouvé.");
            $name = " '" . $p['name'] . "'";
            if ($this->personnageDAO->deletePerso($id) === 0) {
                throw new \Exception("Erreur : Échec de la suppression du personnage avec l'ID $id.");
            }
        } catch (\Exception $e) {
            $this->mainController->index($e->getMessage());
            return;
        }
        $this->mainController->index("Personnage" . ($name ?? '') . " ayant l'ID $id supprimé avec succès !");
    }

    public function displayEditPerso(?string $id = null, ?string $message = null): void {
        try {
            if ($id === null) {
                $message ??= "Erreur : ID de personnage non fourni pour l'édition.";
                throw new \Exception($message);
            }
            $p = $this->personnageDAO->getByID($id);
            if (empty($p))
                throw new \Exception("Erreur : Personnage avec l'ID $id non trouvé.");
            
            $p = PersonnageService::fromRawToPersonnage($p);
        } catch (\Exception $e) {
            $this->mainController->index($e->getMessage());
            return;
        }

        $this->replaceUrlByEditPerso($id);
        echo $this->templates->render('add-perso',
            [
                'p'=> $p,
                'page' => 'Éditer un personnage',
                'gameName' => 'Genshin Impact',
                'message' => $message
            ]
        );
    }

    private function replaceUrlByEditPerso(string $idPerso): void {
        ?><script>
            history.replaceState(null, "", "<?= htmlspecialchars(strtok($_SERVER["REQUEST_URI"], '?'))
                . '?action=edit-perso&id=' . urlencode($idPerso) ?>");
        </script><?php
    }

    public function editPersoAndIndex(array $data = []): void {
        try {
            if (empty($data))
                throw new \Exception("Erreur : Données de personnage non fournies pour la modification.");
            
            $existingPerso = $this->personnageDAO->getByID($data['id']);
            if (empty($existingPerso))
                throw new \Exception("Erreur : Personnage avec l'ID " . $data['id'] . " non trouvé.");

            $p = PersonnageService::fromRawToPersonnage($data);
            $this->personnageDAO->updatePerso($p);
            $this->mainController->index("Personnage '" . $p->getName() . "' modifié avec succès !");
        } catch (\Exception $e) {
            $this->displayEditPerso($data['id'] ?? null, $e->getMessage());
        }
    }
}