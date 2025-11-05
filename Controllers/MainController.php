<?php
namespace Controllers;

use League\Plates\Engine;

class MainController
{
    private Engine $templates;
    
    public function __construct(Engine $templates)
    {
        $this->templates = $templates;
    }

    public function index(): void
    {
        echo $this->templates->render('home', ['gameName' => 'Genshin Impact']);
    }
}