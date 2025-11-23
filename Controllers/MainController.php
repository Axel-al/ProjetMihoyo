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
        echo $this->templates->render('home', array_merge(['gameName' => 'Genshin Impact'], compact('listPersonnages')));
    }
}