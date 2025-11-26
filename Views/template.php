<?php
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$homeUrl = $scheme . '://' . $host . $path;
$addPersoUrl = $homeUrl . '?action=add-perso';
$addPersoElementUrl = $homeUrl . '?action=add-perso-element';
$logsUrl = $homeUrl . '?action=logs';
$loginUrl = $homeUrl . '?action=login';

$navLinks = [
    'Accueil' => $homeUrl,
    'Ajouter un personnage' => $addPersoUrl,
    'Ajouter un élément' => $addPersoElementUrl,
    'Logs' => $logsUrl,
    'Connexion' => $loginUrl,
];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <link rel="stylesheet" href="<?= Config\Paths::publicUrl() ?>/css/main.css"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title><?= $this->e($page ?? 'Accueil') ?></title>
</head>
<?php if (isset($message)) echo $this->insert('message', ['message' => $message]); ?>
<body>
    <header class="top">
        <div class="wrap">
            <a href="<?= $this->e($homeUrl) ?>" class="btn btn-brand">CRUD <?= $this->e($gameName ?? 'Genshin Impact') ?></a>
            <nav class="nav">
                <?php foreach ($navLinks as $label => $url): ?>
                    <?php $activeClass = ($page == $label) ? ' active' : ''; ?>
                    <a href="<?= $this->e($url) ?>" class="btn btn-nav<?= $activeClass ?>">
                        <?= $this->e($label) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </header>

    <main id="contenu">
        <?= $this->section('content') ?>
    </main>

    <footer>
        <!-- TODO: footer -->
    </footer>

    <!-- Slot pour scripts page -->
    <?= $this->section('scripts') ?>
    <script>
    document.addEventListener("DOMContentLoaded", () => {
        document.querySelectorAll("table.xdebug-error").forEach(el => {
            el.style.setProperty("color", "black", "important");
        });
    });
    </script>
</body>
</html>