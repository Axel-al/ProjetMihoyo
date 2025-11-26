<?php
namespace Controllers;

use League\Plates\Engine;
use Models\PersonnageDAO;
use Services\PersonnageService;
use Services\Image\ImageService;

class MainController {
    private Engine $templates;
    
    public function __construct(Engine $templates) {
        $this->templates = $templates;
    }

    public function index(?string $message = null): void {
        $personnageDAO = new PersonnageDAO();
        $listPersonnages = PersonnageService::fromRawToListPersonnages($personnageDAO->getAll());
        ImageService::prepareEntitiesImages($listPersonnages, 'personnages');
        $infosThumbnails = ImageService::prepareThumbnailsForEntities($listPersonnages);
        $this->replaceUrlByHome();
        echo $this->templates->render('home',
            [
                'gameName' => 'Genshin Impact',
                'page' => 'Accueil',
                ...compact(
                    'listPersonnages',
                    'infosThumbnails',
                    'message'
                )
            ]
        );
    }

    private function replaceUrlByHome(): void {
        ?><script>
            history.replaceState(null, "", "<?= htmlspecialchars(strtok($_SERVER["REQUEST_URI"], '?')) ?>");
        </script><?php
    }

    public function displayLogs(): void {
        echo $this->templates->render('logs',
            [
                'page' => 'Logs',
                'gameName' => 'Genshin Impact'
            ]
        );
    }

    public function displayLogin(): void {
        echo $this->templates->render('login',
            [
                'page' => 'Connexion',
                'gameName' => 'Genshin Impact'
            ]
        );
    }
}