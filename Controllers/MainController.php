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

    public function index(): void {
        $personnageDAO = new PersonnageDAO();
        $listPersonnages = PersonnageService::fromRawToListPersonnages($personnageDAO->getAll());
        ImageService::prepareEntitiesImages($listPersonnages, 'personnages');
        $infosThumbnails = ImageService::prepareThumbnailsForEntities($listPersonnages);
        echo $this->templates->render('home',
            [
                'gameName' => 'Genshin Impact'
                , ...compact(
                'listPersonnages',
                'infosThumbnails'
            )]
        );
    }
}