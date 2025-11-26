<?php
$gameName ??= 'Genshin Impact';
$this->layout('template', ['page' => $page ?? 'Accueil', 'gameName' => $gameName, 'message' => $message ?? null]);

$elementColors = [
    'pyro' => ['bg' => '#ff6a00'],
    'hydro' => ['bg' => '#00a3ff'],
    'electro' => ['bg' => '#a64dff'],
    'cryo' => ['bg' => '#6ad0ff'],
    'dendro' => ['bg' => '#7bd96b'],
    'anemo' => ['bg' => '#78e6b6'],
    'geo' => ['bg' => '#d4a82e'],
];

['existing' => $existing, 'pending' => $pending, 'errors' => $thErrors] = $infosThumbnails;
?>

<h1>Collection <?= $this->e($gameName) ?></h1>
<p class="hint">Survoler une carte pour voir la face arrière. Nom + Élément + Rareté sont affichés sur la face avant.</p>

<div class="flip-grid">
    <?php foreach ($listPersonnages ?? [] as $p): ?>
        <?php
            $id = $p->getId();
            $name = $p->getName();
            $el = $p->getElement();
            $unit = $p->getUnitclass();
            $rarity = min(5, max(4, $p->getRarity()));
            $origin = $p->getOrigin();
            $img = $existing[$id]['webUrl'] ?? $pending[$id]['webUrl'] ?? $thErrors[$id]['webUrl'];
            $desc = $p->getDescription();

            $stars = str_repeat('★', $rarity);
            $starsClass = ($rarity == 4) ? 'stars-4' : 'stars-5';
            $styleVars = \Helpers\ViewStyle::getElementStyle($el, $elementColors);

            $thumbInfo = $pending[$id] ?? null;
            $dataThumbAttrs = '';

            if ($thumbInfo !== null) {
                $dataThumbAttrs .= ' data-thumb-job="' . $this->e($thumbInfo['jobId']) . '"';

                if (!empty($thumbInfo['linkWeb']))
                    $dataThumbAttrs .= ' data-thumb-stem="' . $this->e($thumbInfo['linkWeb']) . '"';
            }
        ?>
        <article class="flip-card" tabindex="0" aria-label="<?= $this->e($name) ?>">
            <div class="flip-inner">
                <!-- Face avant -->
                <div class="front flip-face panel">
                    <div class="stars-overlay <?= $starsClass ?>"><?= $stars ?></div>
                    <img class="character-img" src="<?= $this->e($img) ?>" alt="<?= $this->e($name) ?>"<?= $dataThumbAttrs ?>>
                    <div class="title-row">
                        <h3><?= $this->e($name) ?></h3>
                        <span class="el-badge" style="<?= $styleVars ?>"><?= $this->e($el) ?></span>
                    </div>
                </div>

                <!-- Face arrière -->
                <div class="back flip-face panel">
                    <div class="content">
                        <h3><?= $this->e($name) ?></h3>
                        <div class="chips">
                            <span class="chip el" style="<?= $styleVars ?>"><?= $this->e($el) ?></span>
                            <span class="chip"><?= $this->e($unit) ?></span>
                            <?php if (!empty($origin)): ?>
                                <span class="chip"><?= $this->e($origin) ?></span>
                            <?php endif; ?>
                            <span class="chip stars <?= $starsClass ?>"><?= $stars ?></span>
                        </div>
                        <?php if (!empty($desc)): ?>
                            <p class="desc"><?= $this->e($desc) ?></p>
                        <?php endif; ?>
                        <div class="actions">
                            <form action="" method="get">
                                <input type="hidden" name="action" value="edit-perso">
                                <input type="hidden" name="id" value="<?= $this->e($id) ?>">
                                <button class="btn" type="submit">Modifier</button>
                            </form>
                            <form action="" method="get">
                                <input type="hidden" name="action" value="del-perso">
                                <input type="hidden" name="id" value="<?= $this->e($id) ?>">
                                <button class="btn danger" type="submit" onclick="return confirm('Supprimer <?= $this->e($name) ?> ?')">Supprimer</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<?php $this->start('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.flip-card').forEach(card => {
        card.addEventListener('mouseleave', () => {
            const focused = document.activeElement;
            if (card.contains(focused))
                focused.blur();
        });
    });

    document.querySelectorAll('.btn').forEach(btn => {
        btn.addEventListener('mouseleave', () => {
            const focused = document.activeElement;
            if (btn === focused)
                focused.blur();
        });
    });
});
</script>
<?php if (!empty($pending)): ?>
    <script>
        window.THUMB_STATUS_URL = "<?= \Config\Paths::publicUrl() ?>/api/thumb_status.php";
    </script>
    <script src="<?= \Config\Paths::publicUrl() ?>/js/thumb_polling.js"></script>
<?php endif; ?>
<?php $this->stop() ?>