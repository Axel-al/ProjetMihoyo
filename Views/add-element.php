<?php
$page ??= 'Ajouter un élément';
$gameName ??= 'Genshin Impact';
$this->layout('template', ['page' => $page, 'gameName' => $gameName, 'message' => $message ?? null]);
?>
<h1><?= $this->e($page)?></h1>