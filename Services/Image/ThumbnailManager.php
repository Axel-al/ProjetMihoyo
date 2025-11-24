<?php
namespace Services\Image;

use Config\Config;
use Config\Paths;
use Helpers\FileSystem;

/**
 * Service technique bas-niveau responsable de la gestion des thumbnails.
 * Ne manipule pas d'entités métiers - travaille uniquement avec chemins, URLs et dimensions.
 */
class ThumbnailManager {
    private const DEFAULT_BASE_URL = 'http://127.0.0.1:5001';
    private const DEFAULT_ENQUEUE = '/enqueue';
    private const DEFAULT_HEALTH = '/health';
    private const DEFAULT_EXT = '.webp';

    private static ?string $thumbBaseUrl     = null;
    private static ?string $enqueueEndpoint  = null;
    private static ?string $healthEndpoint   = null;
    private static ?string $thumbExtension   = null;

    private static ?bool $isServerReachable = null;

    /**
     * Accès config : base URL du serveur de thumbnails (ne finit pas par '/', lazy cache).
     */
    private static function thumbBaseUrl(): string {
        return self::$thumbBaseUrl ??=
            rtrim(Config::get('thumb_base_url', self::DEFAULT_BASE_URL), '/');
    }

    /**
     * Accès config : endpoint d'enqueue (commence toujours par '/', lazy cache).
     */
    private static function enqueueEndpoint(): string {
        return self::$enqueueEndpoint ??=
            '/' . ltrim(Config::get('enqueue_endpoint', self::DEFAULT_ENQUEUE), '/');
    }

    /**
     * Accès config : endpoint de healthcheck (commence toujours par '/', lazy cache).
     */
    private static function healthEndpoint(): string {
        return self::$healthEndpoint ??=
            '/' . ltrim(Config::get('health_endpoint', self::DEFAULT_HEALTH), '/');
    }

    /**
     * Accès config : extension de thumbnail (commence toujours par '.', lazy cache).
     */
    public static function thumbExtension(): string {
        return self::$thumbExtension ??=
                '.' . ltrim(Config::get('thumb_extension', self::DEFAULT_EXT), '.');
    }

    private static function isServerReachable(): bool {
        if (self::$isServerReachable !== null)
            return self::$isServerReachable;

        $ch = curl_init(self::thumbBaseUrl() . self::healthEndpoint());
        if ($ch === false) {
            trigger_error('Failed to init cURL for check if thumbnail server is reachable', E_USER_WARNING);
            return self::$isServerReachable = false;
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 0.5
        ];
        $cafile = Paths::caBundle();
        if ($cafile !== false)
            $opts[CURLOPT_CAINFO] = $cafile;
        
        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        if ($response === false) {
            $errno = curl_errno($ch);
            if ($errno !== CURLE_COULDNT_CONNECT) {
                trigger_error('Thumbnail server is unreachable: '. curl_strerror($errno)
                    . '(' . $errno . ') - ' . curl_error($ch), E_USER_WARNING);
            }
            
            curl_close($ch);
            return self::$isServerReachable = false;
        }
        return self::$isServerReachable = true;
    }


    /**
     * Construit un ID unique de thumbnail basé sur :
     * - chemin système
     * - mtime du fichier
     * - dimensions (w x h)
     */
    private static function buildJobId(string $srcSysAbs, int $width, int $height): string {
        $srcSysAbs = realpath($srcSysAbs) ?: $srcSysAbs;
        $mtime = is_file($srcSysAbs) ? filemtime($srcSysAbs) : 0;
        return md5($srcSysAbs . '|' . $mtime . '|' . $width . 'x' . $height);
    }

    /**
     * Envoie un job au serveur Python.
     * Ne lève pas d'exception : en cas d'erreur, log soft et continue.
     */
    private static function enqueueJob(string $jobId, string $srcSys, string $dstSys, int $width, int $height): bool {
        if (!self::isServerReachable())
            return false;

        $payload = json_encode([
            'job_id' => $jobId,
            'src' => $srcSys,
            'dst' => $dstSys,
            'width' => $width,
            'height' => $height
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            trigger_error('Thumbnail enqueue JSON error: ' . json_last_error_msg(), E_USER_WARNING);
            return false;
        }

        $ch = curl_init(self::thumbBaseUrl() . self::enqueueEndpoint());
        if ($ch === false) {
            trigger_error('Failed to init cURL for thumbnail enqueue', E_USER_WARNING);
            return false;
        }

        $opts = [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 0.2, // on ne veut PAS bloquer longtemps
            CURLOPT_POSTFIELDS => $payload
        ];
        $cafile = Paths::caBundle();
        if ($cafile !== false)
            $opts[CURLOPT_CAINFO] = $cafile;
        
        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        if ($response === false) {
            $errno = curl_errno($ch);
            trigger_error('Thumbnail enqueue cURL error: ' . curl_strerror($errno)
                . '(' . $errno . ') - ' . curl_error($ch), E_USER_WARNING);
            curl_close($ch);
            return false;
        } else {
            $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            if ($code < 200 || $code >= 300) {
                trigger_error("Thumbnail enqueue HTTP error: {$code} - {$response}", E_USER_WARNING);
                curl_close($ch);
                return false;
            }
        }
        curl_close($ch);
        return true;
    }

    /**
     * Prépare l’affichage d’un thumbnail pour une image.
     *
     * Selon l’état du cache :
     * - si le thumbnail existe déjà : renvoie son URL (prête à être utilisée dans une balise <img>),
     *   ainsi que son jobId et éventuellement un stem lisible pour un symlink ;
     * - sinon : tente d’enfiler un job de génération sur le serveur Python et renvoie
     *   temporairement l’URL de l’image originale.
     *
     * @param string      $urlImg      URL image (chemin web après ImageService)
     * @param int         $width       largeur cible
     * @param int         $height      hauteur cible
     * @param string|null $displayName nom lisible optionnel (pour construire le stem du symlink)
     *
     * Retourne un tableau associatif avec :
     * - `thumbExists` (bool) true si le fichier thumbnail existe déjà sur disque et pas d'erreur
     * - `webUrl` (string)    URL à utiliser dans la balise <img>
     *                        (thumbnail si dispo, sinon image originale)
     * - `jobId` (?string)    identifiant de job si la génération a été enfilée
     *                        ou si le thumb existe ; null en cas d’erreur
     *                        (n'est jamais une chaîne vide)
     * - `linkWeb` (?string)  stem lisible pour le symlink (sans extension)
     *                        si créé ou prévu, null si aucun displayName valide
     *
     * @return array{
     *     thumbExists: bool,
     *     webUrl: string,
     *     jobId: ?string,
     *     linkWeb: ?string
     * }
     */
    public static function getOrQueueThumbnail(string $urlImg, int $width, int $height, ?string $displayName = null): array {
        $imgSys = FileSystem::webToSysPath($urlImg);

        // Si on ne sait pas convertir -> on renvoie l'original
        if ($imgSys === null || !is_file($imgSys))
            return [
                'thumbExists' => false,
                'webUrl' => $urlImg,
                'jobId' => null,
                'linkWeb' => null
            ];

        $jobId = self::buildJobId($imgSys, $width, $height);

        // Dossiers pour les thumbnails
        $thumbDirSys = Paths::imgSysAbs() . '/thumbs_cache';
        $thumbDirUrl = Paths::imgUrl() . '/thumbs_cache';
        FileSystem::ensureDir($thumbDirSys);

        $thumbBase = $jobId . self::thumbExtension();
        $thumbSys = $thumbDirSys . '/' . $thumbBase;
        $thumbUrl = $thumbDirUrl . '/' . $thumbBase;

        // slug = stem lisible à partir du displayName + début de jobId
        $slug = null;
        if ($displayName !== null) {
            $baseSlug = FileSystem::slug($displayName . '_' . substr($jobId, 0, 13));
            $slug = $baseSlug === false ? null : $baseSlug;
        }

        if (is_file($thumbSys)) {
            // Thumbnail déjà présent -> symlink lisible optionnel
            $slugToReturn = null;
            if ($slug !== null) {
                $linksDirSys = Paths::imgSysAbs() . '/thumbs';
                $linksDirUrl = Paths::imgUrl() . '/thumbs';
                FileSystem::ensureDir($linksDirSys);

                $linkSys = $linksDirSys . '/' . $slug . self::thumbExtension();
                $linkUrl = $linksDirUrl . '/' . $slug . self::thumbExtension();

                if (FileSystem::createSymlink($thumbSys, $linkSys)) {
                    $thumbUrl = $linkUrl;
                    $slugToReturn = $slug;
                }
            }

            return [
                'thumbExists' => true,
                'webUrl' => $thumbUrl,
                'jobId' => $jobId,
                'linkWeb' => $slugToReturn // on ne renvoie le stem que si le symlink a bien été créé
            ];
        }

        // Thumbnail non présent -> on envoie le job au serveur Python
        if (!self::enqueueJob($jobId,$imgSys, $thumbSys, $width, $height))
            return [
                'thumbExists' => false,
                'webUrl' => $urlImg,
                'jobId' => null,
                'linkWeb' => null
            ];

        return [
            'thumbExists' => false,
            'webUrl' => $urlImg, // on garde l'original en attendant
            'jobId' => $jobId,
            'linkWeb' => $slug // sera utilisé par thumb_status pour construire le symlink
        ];
    }
}
