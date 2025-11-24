<?php
namespace Services\Image;

use Config\Paths;
use Helpers\FileSystem;

class ImageService {
    public static function downloadImage(string $url, ?string $displayName = null, ?string $group = 'store'): ?string {
        if (!filter_var($url, FILTER_VALIDATE_URL))
            return null;

        $group ??= 'store';
        $group = trim($group, '/');

        $imgSysAbs = Paths::imgSysAbs();
        $imgUrl = Paths::imgUrl();

        $storeSysAbs = $imgSysAbs . '/' . $group . '_cache';
        $linksSysAbs = $imgSysAbs . '/' . $group;

        $storeUrl = $imgUrl . '/' . $group . '_cache';
        $linksUrl = $imgUrl . '/' . $group;

        FileSystem::ensureDir($storeSysAbs);
        FileSystem::ensureDir($linksSysAbs);

        $key = md5($url);
        $existing = glob($storeSysAbs . '/' . $key . '.*');

        if (!empty($existing)) {
            $localPath = $existing[0];
            $ext = pathinfo($localPath, PATHINFO_EXTENSION);
        } else {
            // --- téléchargement ---
            $tmpFile = tempnam($storeSysAbs, 'tmp_');

            $ch   = curl_init($url);
            $file = fopen($tmpFile, 'wb');

            $opts = [
                CURLOPT_FILE           => $file,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT      => 'Mozilla/5.0',
                CURLOPT_TIMEOUT        => 60,
            ];

            $cafile = Paths::caBundle();
            if ($cafile !== false)
                $opts[CURLOPT_CAINFO] = $cafile;

            curl_setopt_array($ch, $opts);

            if (curl_exec($ch) === false) {
                trigger_error("Curl error : " . curl_error($ch), E_USER_WARNING);
                curl_close($ch);
                fclose($file);
                @unlink($tmpFile);
                return null;
            }

            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            fclose($file);

            $ext = FileSystem::mimeToExt($contentType);
            if ($ext === 'bin')
                return null;

            $localPath = $storeSysAbs . '/' . $key . '.' . $ext;
            rename($tmpFile, $localPath);
        }

        if ($displayName !== null) {
            // --- lien "parlant" ---
            $slugName = FileSystem::slug($displayName);
            $symlinkPath = $linksSysAbs . '/' . $slugName . '.' . $ext;

            if ($slugName !== false && FileSystem::createSymlink($localPath, $symlinkPath))
                $finalUrl = $linksUrl . '/' . $slugName . '.' . $ext;
        }

        if (!isset($finalUrl))
            $finalUrl = $storeUrl . '/' . $key . '.' . $ext;

        return $finalUrl;
    }

    public static function prepareEntitiesImages(iterable $entities, ?string $group = 'entities'): void {
        foreach ($entities as $entity) {
            if (!method_exists($entity, 'getUrlImg') || !method_exists($entity, 'setUrlImg'))
                continue;

            $url = $entity->getUrlImg();
            $name = method_exists($entity, 'getName') ? $entity->getName() : null;
            $id = method_exists($entity, 'getId') ? $entity->getId() : null;

            $displayName = ($name !== null && $id !== null)
                ? $name . '_' . $id
                : ($name ?? $id);

            $localUrl = self::downloadImage($url, $displayName, $group);
            if ($localUrl !== null)
                $entity->setUrlImg($localUrl);
        }
    }

    /**
     * Prépare les thumbnails pour une liste d'entitées (getUrlImg / getId / optionnel getName).
     * 
     * Retourne trois tableaux contenant les informations des thumbnails :
     *     - ceux déjà existants,
     *     - ceux en attente de génération,
     *     - ceux ayant rencontré une erreur.
     *
     * @param iterable $entities
     * @param int      $width
     * @param int      $height
     *
     * @return array{
     *     existing: array<string, array{
     *         thumbExists: true,
     *         webUrl: string,
     *         jobId: string,
     *         linkWeb: ?string
     *     }>,
     *     pending:  array<string, array{
     *         thumbExists: false,
     *         webUrl: string,
     *         jobId: string,
     *         linkWeb: ?string
     *     }>,
     *     errors:  array<string, array{
     *         thumbExists: false,
     *         webUrl: string,
     *         jobId: null,
     *         linkWeb: null
     *     }>
     * }
     */
    public static function prepareThumbnailsForEntities(iterable $entities, int $width = 480, int $height = 600): array {
        $existing = [];
        $pending = [];
        $errors = [];
        
        foreach ($entities as $entity) {
            if (!method_exists($entity, 'getUrlImg') || !method_exists($entity, 'getId'))
                continue;

            $urlImg = $entity->getUrlImg();
            $id = $entity->getId();
            $name = method_exists($entity, 'getName') ? $entity->getName() : null;

            $info = ThumbnailManager::getOrQueueThumbnail($urlImg, $width, $height, $name);
            
            if ($info['jobId'] !== null) {
                if ($info['thumbExists'])
                    $existing[$id] = $info;
                else
                    $pending[$id] = $info;
            } else {
                $errors[$id] = $info;
            }
        }

        return [
            'existing' => $existing,
            'pending' => $pending,
            'errors' => $errors
        ];
    }
}
