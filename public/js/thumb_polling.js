
document.addEventListener('DOMContentLoaded', () => {
    const DEBUG_THUMBS = false;
    const dbg = (...args) => {
        if (DEBUG_THUMBS) {
            console.log('[THUMB]', ...args);
        }
    };

    const pendingImgs = Array.from(
        document.querySelectorAll('.character-img[data-thumb-job]')
    );

    dbg('Images avec thumbnails en attente :', pendingImgs.length);

    if (pendingImgs.length !== 0) {
        const pollDelay = 3000;
        let pollTimer = null;

        function buildJobsPayload() {
            const jobs = [];

            pendingImgs.forEach(img => {
                const jobId = img.dataset.thumbJob;
                if (!jobId) return;

                jobs.push({
                    jobId: jobId,
                    stem: img.dataset.thumbStem || null
                });
            });

            dbg('Payload jobs construit :', jobs);
            return jobs;
        }

        function pollThumbnails() {
            const jobs = buildJobsPayload();

            if (jobs.length === 0) {
                dbg('Plus aucun job avec data-thumb-job, arrêt du polling.');
                if (pollTimer !== null) {
                    clearInterval(pollTimer);
                    pollTimer = null;
                }
                return;
            }

            dbg('Envoi requête thumb_status.php avec', jobs.length, 'jobs');

            fetch(window.THUMB_STATUS_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                cache: 'no-store',
                body: JSON.stringify({ jobs })
            })
            .then(resp => {
                dbg('Réponse HTTP reçue :', resp.status);
                if (!resp.ok) {
                    dbg('Réponse non OK, arrêt traitement pour ce tour.');
                    return null;
                }
                return resp.json();
            })
            .then(data => {
                if (!data) {
                    dbg('Aucune donnée JSON (data=null).');
                    return;
                }

                dbg('JSON reçu depuis thumb_status.php :', data);

                if (!data.items) {
                    dbg('Champ "items" manquant dans la réponse.');
                    return;
                }

                const items = data.items;

                pendingImgs.forEach(img => {
                    const jobId = img.dataset.thumbJob;
                    if (!jobId) return;

                    const info = items[jobId];
                    if (!info) {
                        dbg('Aucune info pour jobId =', jobId, '(peut-être ignoré côté serveur)');
                        return;
                    }

                    dbg('Job', jobId, '->', info);
                    
                    if (info.status === 'ready' && info.webUrl) {
                        dbg('Thumbnail prêt pour job', jobId, '=>', info.webUrl);
                        img.src = info.webUrl;
                        img.removeAttribute('data-thumb-job');
                        img.removeAttribute('data-thumb-stem');
                    }
                });

                const stillPending = pendingImgs.some(img => img.dataset.thumbJob);
                dbg('Reste-t-il des thumbnails en attente ?', stillPending);

                if (!stillPending && pollTimer !== null) {
                    dbg('Plus aucun thumbnail en attente, arrêt définitif du polling.');
                    clearInterval(pollTimer);
                    pollTimer = null;
                }
            })
            .catch(err => {
                dbg('Erreur réseau ou fetch thumb_status.php :', err);
            });
        }

        dbg('Démarrage du polling thumbnails (interval =', pollDelay, 'ms)');
        pollThumbnails();
        pollTimer = setInterval(pollThumbnails, pollDelay);
    } else {
        dbg('Aucun thumbnail à surveiller, arrêt.');
    }
});