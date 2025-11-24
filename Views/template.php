<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <link rel="stylesheet" href="<?= Config\Paths::publicUrl() ?>/css/main.css"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title><?= $this->e($title ?? 'App') ?></title>

</head>
<body>
    <header>
        <nav>
            <!-- TODO: menu -->
        </nav>
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