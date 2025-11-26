<?php
namespace Controllers;

use League\Plates\Engine;

class ElementController {
    private Engine $templates;
    
    public function __construct(Engine $templates) {
        $this->templates = $templates;
    }

    public function displayAddElement(): void {
        echo $this->templates->render('add-element',
            [
                'page' => 'Ajouter un élément',
                'gameName' => 'Genshin Impact'
            ]
        );
    }
}