<?php
namespace Config;

class Paths {
    private static ?string $projectRoot = null;
    private static ?string $publicSysAbs = null;
    private static ?string $publicUrl = null;
    private static ?string $imgSysAbs = null;
    private static ?string $imgUrl = null;
    private static null|string|false $caBundle = null;
    
    public static function projectRoot(): string {
        return self::$projectRoot ??= realpath(__DIR__ . '/..') ?: __DIR__ . '/..';
    }

    public static function publicSysAbs(): string {
        return self::$publicSysAbs ??= self::projectRoot() . '/public';
    }

    /**
     * Retourne l'URL root-relative correspondant au répertoire `public/` de l’application.
     *
     * @see self::computePublicUrl() pour la logique détaillée.
     * 
     * Sources utilisées :
     * -------------------
     * - Config::get('public_url'), si défini ;
     * - sinon :
     *     - $_SERVER['DOCUMENT_ROOT'] (racine web côté serveur),
     *     - self::publicSysAbs() (chemin système absolu du dossier `public`).
     *
     * Mode 1 : public_url défini dans la config
     * ----------------------------------------
     * - Si Config::get('public_url') est non null et ne devient pas vide après trim(),
     *   cette valeur est utilisée en priorité.
     * - On considère qu’il s’agit d’un chemin web absolu (root-relative), sans nom
     *   de domaine, par exemple :
     *     - "/public"
     *     - "/mon/app/public"
     *     - "public" (sera normalisé en "/public")
     * - La valeur retournée par publicUrl() dans ce mode est :
     *     - soit la chaîne vide "" si public_url équivaut à "/" (ou seulement des "/"),
     *     - soit une chaîne du type "/quelque/chose" sans "/" final.
     * - Aucun contrôle n’est fait par rapport à $_SERVER['DOCUMENT_ROOT'] dans ce mode :
     *   c’est à l’utilisateur de fournir un chemin cohérent avec sa config (alias, vhost,
     *   reverse proxy, etc.).
     *
     * Mode 2 : déduction automatique via DOCUMENT_ROOT
     * -----------------------------------------------
     * - Ce mode est utilisé si Config::get('public_url') est null ou bien équivaut
     *   à une chaîne vide après trim() (par exemple "", "   ").
     * - La fonction s’appuie alors sur :
     *     - $_SERVER['DOCUMENT_ROOT'] (fourni par le serveur),
     *     - self::publicSysAbs() (chemin absolu de `public` dans le projet).
     * - Hypothèses nécessaires pour que le calcul soit valide (hypothèses
     *   vraies la plupart du temps) :
     *     - $_SERVER['DOCUMENT_ROOT'] est défini, non vide, et correspond à la
     *       racine web réelle (ou à un chemin résolu équivalent) ;
     *     - le répertoire `public` se trouve physiquement sous DOCUMENT_ROOT
     *       dans l’arborescence du système de fichiers, c’est-à-dire que
     *       publicSysAbs() commence bien par DOCUMENT_ROOT en tant que chaîne ;
     *     - ce lien est direct (répertoire ou sous-répertoire) et non via un alias
     *       ou vhost qui pointerait vers un autre arbre complètement distinct ;
     *     - si `public` est lui-même un symlink, il doit pointer vers un
     *       répertoire qui se trouve également sous DOCUMENT_ROOT.
     * - Le cas particulier où DOCUMENT_ROOT == le dossier `public` est
     *   supporté : publicUrl() renverra alors la chaîne vide "".
     * - L’utilisation éventuelle de symlinks à l’intérieur de `./public`
     *   (pour ses sous-dossiers/fichiers) n’a aucun impact : seuls
     *   DOCUMENT_ROOT et le chemin de `public` sont pris en compte.
     *
     * Garanties de retour
     * -------------------
     * - publicUrl() retourne toujours :
     *     - soit la chaîne vide "" (point déja sur `public`),
     *     - soit une chaîne de la forme "/chemin/sans/slash/final".
     * - Toujours sûre pour la concaténation, ex :
     *       self::publicUrl() . '/img'
     *
     * Exceptions
     * ----------
     * - En mode auto (sans public_url exploitable), une \Exception est levée si :
     *     - $_SERVER['DOCUMENT_ROOT'] est absent ou invalide ;
     *     - ou si le chemin de `public` (self::publicSysAbs()) ne se trouve
     *       manifestement pas sous DOCUMENT_ROOT (par exemple, autre arborescence
     *       ou préfixe de chemin simplement similaire mais non ancêtre réel).
     *
     * @return string URL root-relative vers le répertoire public (jamais null)
     *
     * @throws \Exception Si l’une des conditions suivantes est remplie :
     *     - Si public_url est vide après trim()
     *     - Si DOCUMENT_ROOT est absent ou invalide en mode auto
     *     - Si `public` ne se trouve pas sous DOCUMENT_ROOT une fois les chemins
     *       résolus en mode auto
     */
	public static function publicUrl(): string {
        return self::$publicUrl ??= self::computePublicUrl();
    }

    public static function imgSysAbs(): string {
        return self::$imgSysAbs ??= self::publicSysAbs() . '/img';
    }

    public static function imgUrl(): string {
        return self::$imgUrl ??= self::publicUrl() . '/img';
    }

    public static function caBundle(): string|false {
        return self::$caBundle ??= (function() {
            $relative = Config::get('ca_bundle');
            if ($relative === null)
                return false;
            return self::projectRoot() . '/' . ltrim($relative, '/.');
        })();
    }

    /**
     * Calcule l'URL publique root-relative du dossier `public/`.
     *
     * Implémentation interne appelée par publicUrl().
     * Peut lever une Exception si la configuration ou DOCUMENT_ROOT est incohérente.
     *
     * @return string
     * @throws \Exception
     *
     * @internal
     */
    private static function computePublicUrl(): string {
        $isConfigured = false;
        
        $configured = Config::get('public_url');
        if ($configured !== null) {
            $value = trim($configured);

            if ($value !== '')
                $isConfigured = true;
            
            $value = rtrim($value, '/');
        }

        if (!$isConfigured) {				
            if (empty($_SERVER['DOCUMENT_ROOT']) || !is_string($_SERVER['DOCUMENT_ROOT'])) {
                throw new \Exception(
                    'Paths::publicUrl() requires $_SERVER["DOCUMENT_ROOT"] to be defined when no Config::get("public_url") is set.'
                );
            }

            $docRoot = realpath($_SERVER['DOCUMENT_ROOT']);
            if ($docRoot === false) {
                throw new \Exception(
                    'Paths::publicUrl() could not resolve DOCUMENT_ROOT "' . $_SERVER['DOCUMENT_ROOT'] . '" with realpath().'
                );
            }
            $docRoot = str_replace('\\', '/', $docRoot);
            $docRoot = rtrim($docRoot, '/');
            
            $publicAbs = realpath(self::publicSysAbs());
            if ($publicAbs === false) {
                throw new \Exception(
                    'Paths::publicUrl() could not resolve Paths::publicSysAbs() "' . self::publicSysAbs() . '" with realpath().'
                );
            }
            $publicAbs = str_replace('\\', '/', $publicAbs);

            if (!str_starts_with($publicAbs, $docRoot)) {
                throw new \Exception(
                    'Paths::publicUrl() expects publicSysAbs ("' . self::publicSysAbs() . '") to be inside'
                    . 'DOCUMENT_ROOT ("' . $_SERVER['DOCUMENT_ROOT'] . '") when public_url is not configured.'
                );
            }

            $value = substr($publicAbs, strlen($docRoot));

            // Si ce n'est pas une chaine vide et qu'on n'a pas un "/" initial alors c'est que publicAbs
            // contient dans son chemin un répertoire qui commence pareil que le répertoire de DOCUMENT_ROOT.
            if ($value !== '' && $value[0] !== '/') {
                throw new \Exception(
                    'Paths::publicUrl() expects publicSysAbs ("' . self::publicSysAbs() . '") to be inside'
                    . 'DOCUMENT_ROOT ("' . $_SERVER['DOCUMENT_ROOT'] . '") when public_url is not configured.'
                );
            }
        }

        if (!empty($value))
            $value = '/' . trim($value, '/');

        return $value;
    }
}
