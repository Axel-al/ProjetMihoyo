<?php
$page ??= 'Ajouter un personnage';
$gameName ??= 'Genshin Impact';
$this->layout('template', ['page' => $page, 'gameName' => $gameName, 'message' => $message ?? null]);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$homeUrl = $scheme . '://' . $host . $path;
$addPersoUrl = $homeUrl . '?action=add-perso';
$editPersoUrl = $homeUrl . '?action=edit-perso';

$p = isset($p) && $p instanceof Models\Personnage ? $p : null;

// listes contrôlées
$validElements = ['Anémo','Géo','Électro','Dendro','Hydro','Pyro','Cryo','Adaptatif'];
$validClasses  = ["Épée à une main","Épee à deux mains","Arme d'hast","Catalyseur","Arc"];
$validOrigins  = [null,'Mondstadt','Liyue','Inazuma','Sumeru','Fontaine','Natlan','Nod-Krai','Snezhnaya'];

// Pré-remplissage de base à partir du Personnage, si présent
$values = [];
if ($p !== null) {
    $values = [
        'id' => $p->getId(),
        'name' => $p->getName(),
        'element' => $p->getElement(),
        'unitclass' => $p->getUnitclass(),
        'rarity' => (string) $p->getRarity(),
        'origin' => $p->getOrigin() ?? '',
        'urlImg' => $p->getUrlImg(),
        'description' => $p->getDescription() ?? '',
    ];
}
?>

<h1><?= $this->e($page) ?></h1>

<form class="panel form-panel" action="<?= $this->e($p === null ? $addPersoUrl : $editPersoUrl) ?>" method="post">
    <?php if ($p !== null) : ?>
        <input type="hidden" name="id" value="<?= $this->e($values['id']) ?>">
    <?php endif; ?>

    <div class="form-group">
        <label for="name">Nom <span aria-hidden="true" title="obligatoire">*</span></label>
        <input
            id="name"
            name="name"
            type="text"
            required
            maxlength="255"
            value="<?= $this->e($values['name'] ?? '') ?>"
            placeholder="ex. Diluc"
            class="btn input-full"
        >
        <small class="hint">Max 255 caractères.</small>
    </div>

    <div class="form-group">
        <label for="element">Élément <span aria-hidden="true" title="obligatoire">*</span></label>
        <select id="element" name="element" required class="btn select-full">
            <option value="" disabled <?= empty($values['element']) ? 'selected' : '' ?>>Choisir un élément…</option>
            <?php foreach ($validElements as $el): ?>
                <option value="<?= $this->e($el) ?>" <?= ($values['element'] ?? null) === $el ? 'selected' : '' ?>>
                    <?= $this->e($el) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small class="hint">Autorisés : <?= $this->e(implode(', ', $validElements)) ?>.</small>
    </div>

    <div class="form-group">
        <label for="unitclass">Classe d’arme <span aria-hidden="true" title="obligatoire">*</span></label>
        <select id="unitclass" name="unitclass" required class="btn select-full">
            <option value="" disabled <?= empty($values['unitclass']) ? 'selected' : '' ?>>Choisir une classe…</option>
            <?php foreach ($validClasses as $uc): ?>
                <option value="<?= $this->e($uc) ?>" <?= ($values['unitclass'] ?? null) === $uc ? 'selected' : '' ?>>
                    <?= $this->e($uc) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small class="hint">Autorisées : <?= $this->e(implode(', ', $validClasses)) ?>.</small>
    </div>

    <div class="form-group">
        <label for="rarity">Rareté <span aria-hidden="true" title="obligatoire">*</span></label>
        <select id="rarity" name="rarity" required class="btn select-full">
            <option value="" disabled <?= empty($values['rarity']) ? 'selected' : '' ?>>Choisir une rareté…</option>
            <option value="4" <?= ($values['rarity'] ?? null) === '4' ? 'selected' : '' ?>>4 ★</option>
            <option value="5" <?= ($values['rarity'] ?? null) === '5' ? 'selected' : '' ?>>5 ★</option>
        </select>
        <small class="hint">Seulement 4 ★ ou 5 ★.</small>
    </div>

    <div class="form-group">
        <label for="origin">Origine (optionnel)</label>
        <select id="origin" name="origin" class="btn select-full">
            <option value="" <?= empty($values['origin']) ? 'selected' : '' ?>>— Aucune —</option>
            <?php foreach ($validOrigins as $og): ?>
                <?php if ($og === null) continue; ?>
                <option value="<?= $this->e($og) ?>" <?= ($values['origin'] ?? null) === $og ? 'selected' : '' ?>>
                    <?= $this->e($og) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small class="hint">Autorisées : <?= $this->e(implode(', ', array_filter($validOrigins))) ?>.</small>
    </div>

    <div class="form-group">
        <label for="urlImg">URL de l’image <span aria-hidden="true" title="obligatoire">*</span></label>
        <input
            id="urlImg"
            name="urlImg"
            type="url"
            required
            maxlength="255"
            value="<?= $this->e($values['urlImg'] ?? '') ?>"
            placeholder="https://…"
            class="btn input-full"
        >
        <small class="hint">URL valide — max 255 caractères.</small>
    </div>

    <div class="form-group form-group-lg">
        <label for="description">Description (optionnel)</label>
        <textarea
            id="description"
            name="description"
            rows="5"
            class="btn textarea-full"
            placeholder="Quelques infos sur le personnage…"
        ><?= $this->e($values['description'] ?? '') ?></textarea>
    </div>

    <div class="actions">
        <button class="btn" type="submit">
            <?= $this->e($p === null ? 'Créer le personnage' : 'Enregistrer les modifications') ?>
        </button>
        <a class="btn danger" href="<?= $this->e($homeUrl) ?>">
            Annuler
        </a>
    </div>
</form>