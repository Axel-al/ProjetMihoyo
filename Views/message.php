<?php if (isset($message)): ?>
    <?php
    $lower = mb_strtolower($message, encoding: 'UTF-8');
    $isError =
        str_contains($lower, 'exception') ||
        str_contains($lower, 'error') ||
        str_contains($lower, 'erreur');
    ?>

    <div id="notification"
         class="notification<?= $isError ? ' error' : '' ?>"
         data-autoclose="<?= $isError ? '0' : '1' ?>">
        <span><?= $this->e($message) ?></span>
        <button id="closeNotification" class="close-btn">&times;</button>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const notification = document.getElementById('notification');
        if (!notification) return;

        const closeNotification = document.getElementById('closeNotification');

        // fermeture manuelle
        closeNotification.addEventListener('click', () => {
            notification.classList.add('fade-out');
            setTimeout(() => {
                notification.style.display = 'none';
            }, 500);
        });

        // auto-fermeture si non erreur
        if (notification.dataset.autoclose === '1') {
            setTimeout(() => closeNotification.click(), 3000);
        }
    });
    </script>
<?php endif; ?>