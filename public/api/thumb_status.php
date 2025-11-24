<?php
// thumb_status.php
use Config\Paths;
use Helpers\FileSystem;
use Services\Image\ThumbnailManager;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/../../Helpers/Psr4AutoloaderClass.php';

$loader = new Helpers\Psr4AutoloaderClass();
$loader->register();
$loader->addNamespace('Config', __DIR__ . '/../../Config');
$loader->addNamespace('Helpers', __DIR__ . '/../../Helpers');
$loader->addNamespace('Services', __DIR__ . '/../../Services');

$thumbExt = ThumbnailManager::thumbExtension();

/**
 * Envoie une réponse JSON et termine le script.
 */
function json_response(int $statusCode, array $data): void {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, [
        'error' => 'Method Not Allowed. Use POST with JSON body.',
    ]);
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (!str_contains($contentType, 'application/json')) {
    json_response(400, [
        'error' => 'Invalid Content-Type. Expected application/json.',
    ]);
}

$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    json_response(400, [
        'error' => 'Empty request body.',
    ]);
}

try {
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    json_response(400, [
        'error' => 'Malformed JSON: ' . $e->getMessage(),
    ]);
}

if (empty($data['jobs']) || !is_array($data['jobs'])) {
    json_response(400, [
        'error' => 'Missing or invalid "jobs" array.',
    ]);
}

$thumbDirSys  = Paths::imgSysAbs() . '/thumbs_cache';
$thumbDirUrl  = Paths::imgUrl() . '/thumbs_cache';
$linksDirSys  = Paths::imgSysAbs() . '/thumbs';
$linksDirUrl  = Paths::imgUrl() . '/thumbs';

$items = [];

foreach ($data['jobs'] as $job) {
    // Chaque job doit au minimum contenir un jobId
    if (!is_array($job) || empty($job['jobId']))
        continue;

    $jobId = (string) $job['jobId'];
    $stem  = !empty($job['stem']) ? (string) $job['stem'] : null;

    $thumbSys = $thumbDirSys . '/' . $jobId . $thumbExt;

    if (is_file($thumbSys)) {
        $webUrl = $thumbDirUrl . '/' . $jobId . $thumbExt;

        if ($stem !== null) {
            FileSystem::ensureDir($linksDirSys);

            $linkSys = $linksDirSys . '/' . $stem . $thumbExt;
            $linkUrl = $linksDirUrl . '/' . $stem . $thumbExt;

            if (FileSystem::createSymlink($thumbSys, $linkSys))
                $webUrl = $linkUrl;
        }

        $items[$jobId] = [
            'status' => 'ready',
            'webUrl' => $webUrl,
        ];
        continue;
    }

    $items[$jobId] = [
        'status' => 'pending',
        'webUrl' => null,
    ];
}

json_response(200, [
    'items' => $items,
]);
