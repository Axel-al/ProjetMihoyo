````markdown
# CRUD Genshin – PHP + serveur de thumbnails Python

Ce dépôt contient une petite application **CRUD de personnages Genshin Impact** en PHP, avec :

- un front en **League Plates** + CSS custom ;
- un backend en **PDO MySQL** ;
- un système de **téléchargement d’images** côté PHP ;
- un **serveur Python** séparé pour générer des *thumbnails* (vignettes) et un polling JS.

Ce README explique **tout ce qu’il faut faire pour lancer le projet**, côté PHP **et** côté Python (thumbnails).

---

## 1. Prérequis

### Côté PHP

- PHP 8.x (au moins 8.1 conseillé)
- Extensions PHP activées :
  - `pdo_mysql`
  - `curl`
  - `mbstring`
  - (optionnel) `openssl` pour HTTPS
- Un serveur web ou simplement le serveur embarqué PHP :
  - Apache / Nginx **ou**
  - `php -S` pour le dev local
- Une base de données MySQL/MariaDB accessible.

### Côté Python (thumbnails)

Pour la génération de thumbnails :

- Python 3.10+ (recommandé)
- `git` (optionnel) et **Git LFS** (fortement recommandé, voir plus bas)
- Un environnement virtuel (géré automatiquement par `tools/vision/setup_env.py`)

---

## 2. Structure générale

Racine du projet :

- `index.php` : **front controller** PHP
- `Config/` : configuration PHP
- `Views/`, `Controllers/`, `Models/`, `Services/`, `Helpers/` : code de l’appli
- `public/` : ressources publiques  
  (CSS, JS, images, API HTTP pour le polling de thumbnails)

Le front appelle toujours `index.php`, qui utilise le `Router` pour diriger vers les bonnes vues.

---

## 3. Configuration PHP (`Config/dev_sample.ini`)

### 3.1. Quel fichier utiliser ?

Le code charge la config dans cet ordre :

1. `Config/prod.ini` (si présent)  
2. sinon `Config/dev.ini`  
3. sinon → **erreur** : aucun fichier de configuration trouvé.

En dev, le plus simple :

1. Copier le fichier d’exemple  
   ```bash
   cp Config/dev_sample.ini Config/dev.ini
````

2. Éditer `Config/dev.ini` selon votre environnement.

En prod, même principe avec `prod.ini` si besoin.

---

### 3.2. Section `[DB]` – Connexion MySQL

Extrait de `Config/dev_sample.ini` :

```ini
[DB]
dsn = 'mysql:host=localhost;dbname=YOURDBNAME;charset=utf8';
user = 'YOUR_USERNAME';
pass = 'YOUR_PASSWORD';
```

* `dsn` : DSN PDO complet

  * `host` : hôte MySQL (ex. `localhost`, `127.0.0.1`, `mysql`, etc.)
  * `dbname` : **nom de la base** à utiliser (ex. `mihoyo`)
  * `charset` : laissez `utf8` ou `utf8mb4`
* `user` : utilisateur MySQL ayant les droits de **CREATE TABLE / INSERT**.
* `pass` : mot de passe MySQL.

> À la première utilisation, `Models\DatabaseInitializer` créera la table `PERSONNAGE` si elle n’existe pas encore et la remplira à partir de `data/genshin_characters.json`.

---

### 3.3. Section `[Paths]` – URLs publiques & certificat SSL

```ini
[Paths]
public_url = '/ABSOLUTE_WEB_PATH_TO_PUBLIC'
ca_bundle = 'Config/ssl/cacert.pem'
```

* `public_url`

  * URL **root-relative** vers le dossier `public/`.
  * Exemples :

    * `/public`
    * `/mon-app/public`
    * ou `''` (chaîne vide) pour laisser la détection automatique.
  * Si vous laissez vide, `Paths::publicUrl()` tentera de déduire ce chemin à partir de `$_SERVER['DOCUMENT_ROOT']` et du chemin réel du dossier `public/`.

  **Cas typique en dev avec `php -S` :**

  * Vous lancez le serveur depuis la racine du projet (avec `index.php`) :
    → `DOCUMENT_ROOT` = racine du projet
    → `public` est un sous-dossier → `public_url` déduit automatiquement en `/public`.

* `ca_bundle`

  * Chemin **relatif depuis la racine du projet** vers un fichier de certificats CA (par ex. un `cacert.pem`).
  * Utilisé pour sécuriser les requêtes HTTPS dans :

    * `ImageService` (téléchargement des images)
    * `ThumbnailManager` (requêtes vers le serveur Python, si HTTPS)
  * Si vous ne voulez pas/plus vérifier les certificats, vous pouvez **commenter cette ligne** ou la supprimer : `Paths::caBundle()` retournera `false` et le code PHP n’essaiera pas de renseigner `CURLOPT_CAINFO`.

---

### 3.4. Section `[Thumbnails]` – serveur Python

```ini
[Thumbnails]
thumb_base_url = 'http://127.0.0.1:5001';
enqueue_endpoint = '/enqueue';
health_endpoint = '/health';
thumb_extension = '.webp';
```

* `thumb_base_url` :

  * URL de base du serveur Python qui génère les thumbnails
  * Par défaut : `http://127.0.0.1:5001`
* `enqueue_endpoint` :

  * endpoint HTTP pour **enfiler un job de génération** (POST JSON)
* `health_endpoint` :

  * endpoint HTTP pour le **healthcheck** (rapidement testé au chargement)
* `thumb_extension` :

  * extension des fichiers de thumbnails (ex. `.webp`)

Vous n’avez généralement pas besoin de modifier `enqueue_endpoint` / `health_endpoint`, seulement `thumb_base_url` si le serveur écoute ailleurs.

---

## 4. Installation & lancement de l’appli PHP

### 4.1. Cloner le projet

```bash
git clone <url_du_repo> mihoyo-crud
cd mihoyo-crud
```

### 4.2. Configurer la base de données

1. Créez une base MySQL (nom au choix, ex. `mihoyo`) :

   ```sql
   CREATE DATABASE mihoyo CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
   ```
2. Éditez `Config/dev.ini` :

   ```ini
   [DB]
   dsn = 'mysql:host=localhost;dbname=mihoyo;charset=utf8mb4';
   user = 'root';
   pass = '';
   ```
3. Assurez-vous que le fichier `data/genshin_characters.json` est présent (il sert à remplir la table `PERSONNAGE` à la première initialisation).

> `Models\PersonnageDAO` appelle `DatabaseInitializer`, qui :
>
> * crée la table `PERSONNAGE` si nécessaire,
> * l’initialise à partir du JSON si elle est vide.

### 4.3. Configurer les paths publics (CSS/JS/images)

Dans la majorité des cas, vous pouvez :

* soit **laisser `public_url` vide** et lancer PHP depuis la racine du projet (voir §4.4)
* soit mettre explicitement :

  ```ini
  public_url = '/public'
  ```

L’important est que les URLs générées, par exemple :

* `<?= Config\Paths::publicUrl() ?>/css/main.css`
* `<?= Config\Paths::publicUrl() ?>/js/thumb_polling.js`

pointent bien vers le dossier `public/`.

### 4.4. Lancer le serveur PHP (dev)

Depuis la racine du projet :

```bash
php -S localhost:8000 index.php
```

Puis allez sur :

* [http://localhost:8000](http://localhost:8000)

Le `index.php` racine jouera le rôle de front controller, et les ressources situées sous `public/` seront servies par le serveur embarqué (CSS/JS/images…).

---

## 5. Mise en place du serveur Python (thumbnails)

La gestion des thumbnails est **optionnelle pour le fonctionnement de base du CRUD**, mais indispensable si vous voulez :

* générer et afficher des vignettes locales allégées,
* éviter de charger systématiquement des images distantes lourdes.

Si le serveur Python n’est pas lancé ou inaccessible :

* le code de `ThumbnailManager` renverra simplement les URLs d’images originales
* `jobId` sera `null` → pas de polling JS
* l’appli reste fonctionnelle, mais **sans thumbnails générés**.

### 5.1. Modèles (weights) – Git LFS ou téléchargement manuel

Le dépôt Python utilise normalement **Git LFS** pour stocker les modèles :

* `ssd_anime_face_detect.pth`
* `yolov8x6_animeface.pt`

#### Option A – Avec Git LFS (recommandé)

Depuis la racine du projet :

```bash
git lfs install
git lfs pull
```

Les modèles seront alors récupérés dans `tools/vision/models`.

#### Option B – Sans Git LFS

Si vous n’avez pas Git LFS :

1. Téléchargez les fichiers manuellement :

   * `ssd_anime_face_detect.pth`
     depuis
     `https://cdn.jsdelivr.net/gh/XavierJiezou/anime-face-detection@master/model/ssd_anime_face_detect.pth`

   * `yolov8x6_animeface.pt`
     depuis
     `https://huggingface.co/Fuyucchi/yolov8_animeface/resolve/main/yolov8x6_animeface.pt?download=true`

2. Placez-les dans :

   ```text
   ./tools/vision/models/
     ssd_anime_face_detect.pth
     yolov8x6_animeface.pt
   ```

---

### 5.2. Création de la venv & installation des dépendances

Un script dédié gère tout : `tools/vision/setup_env.py`.

Usage :

```text
usage: setup_env.py [-h] [--cpu | --cuda-version CUDA_VERSION | --cuda-tag CUDA_TAG]
                    [--torch-version TORCH_VERSION]
                    [--torchvision-version TORCHVISION_VERSION]

Create venv and install PyTorch (GPU/CPU) + requirements.
```

#### Étapes recommandées

Depuis la racine du projet (ou directement dans `tools/vision`) :

```bash
cd tools/vision
python setup_env.py
```

* Par défaut, le script va :

  * créer un **virtualenv** (souvent `.venv` dans `tools/vision`),
  * installer `torch` / `torchvision` + les autres modules listés dans `requirements.txt`.

#### Options utiles

* `--cpu` : force l’installation de la version CPU de PyTorch (pas de CUDA)
* `--cuda-version 11.8` : force une version de CUDA donnée
* `--cuda-tag cu118` : force un « tag » CUDA particulier pour PyTorch
* `--torch-version 2.2.2`
* `--torchvision-version 0.17.2`

Exemples :

```bash
# Forcer une installation uniquement CPU
python setup_env.py --cpu

# Forcer une version de torch + tag CUDA précis
python setup_env.py --torch-version 2.2.2 --cuda-tag cu118
```

#### En cas d’échec de `setup_env.py`

Si le script échoue (problèmes de dépendances, de CUDA, etc.) :

1. **Vous pouvez réessayer avec d’autres options**, par exemple :

   * `--cpu`
   * une autre combinaison `--torch-version` / `--torchvision-version`

2. Ou bien installer **manuellement** :

   * Créez une venv :

     ```bash
     python -m venv .venv
     source .venv/bin/activate   # (Linux/macOS)
     # ou .venv\Scripts\activate sur Windows
     ```
   * Installez les dépendances :

     ```bash
     pip install -r requirements.txt
     ```
   * Installez éventuellement PyTorch séparément suivant les instructions officielles (CPU ou CUDA).

Le README des outils Python (s’il existe) pourra détailler plus finement les versions conseillées.

---

### 5.3. Lancer le serveur de thumbnails Python

Une fois la venv prête et les modèles présents :

1. Activez la venv (si ce n’est pas déjà fait) :

   ```bash
   cd tools/vision
   source .venv/bin/activate       # Linux/macOS
   # ou .venv\Scripts\activate.bat # Windows
   ```

2. Lancez le serveur Python (nom typique) :

   ```bash
   python thumb_server.py
   ```

   * Il doit écouter sur l’URL indiquée dans `thumb_base_url` (ex. `http://127.0.0.1:5001`).
   * Il expose au minimum :

     * `GET /health` → pour que PHP teste sa disponibilité
     * `POST /enqueue` → pour recevoir les jobs (JSON)

3. **Important :**
   Lancez ce serveur **avant** de charger la page d’accueil du site si vous voulez que la gestion des thumbnails fonctionne « dès le premier chargement ».
   Sinon, le PHP ne pourra pas envoyer de job et les thumbnails seront ignorés (fallback sur les images originales).

---

## 6. Comment fonctionne la chaîne de thumbnails ?

Résumé rapide :

1. `ImageService::downloadImage()` télécharge les images distantes dans `public/img/{group}_cache` et crée éventuellement des symlinks lisibles dans `public/img/{group}`.

2. `ThumbnailManager::getOrQueueThumbnail()` :

   * convertit l’URL web en chemin système avec `FileSystem::webToSysPath()` ;
   * génère un `jobId` unique (en fonction du chemin + mtime + dimensions) ;
   * si le thumbnail existe déjà dans `public/img/thumbs_cache`, renvoie son URL ;
   * sinon, tente un **healthcheck** puis un enqueue (`/enqueue`) sur le serveur Python :

     * en cas de succès : renvoie l’URL originale, mais avec un `jobId` → JS commencera à poller ;
     * en cas d’échec : renvoie l’URL originale **sans** `jobId` → pas de polling (fallback simple).

3. Dans `Views/home.php` :

   * si `jobId` est présent → les balises `<img>` reçoivent des attributs `data-thumb-job` et éventuellement `data-thumb-stem`.

4. `public/js/thumb_polling.js` :

   * collecte toutes les images avec `data-thumb-job`,
   * envoie périodiquement (toutes les 3 s) des requêtes POST JSON à `public/api/thumb_status.php`,
   * dès qu’un job passe en `status = "ready"` avec une `webUrl` :

     * il met à jour `src` de l’image,
     * supprime les `data-thumb-*`,
     * arrête le polling quand plus aucun job n’est en attente.

---

## 7. Dépannage & points d’attention

* **Erreur : Aucun fichier de configuration trouvé**

  * Vérifiez que `Config/dev.ini` ou `Config/prod.ini` existe.
  * Vérifiez les droits de lecture.

* **Problèmes d’URL CSS/JS**

  * Vérifiez la valeur de `public_url`.
  * Vérifiez la cohérence entre `DOCUMENT_ROOT` et la position du dossier `public/`.

* **Erreur cURL (images ou thumbnails)**

  * Vérifiez votre connexion internet.
  * Vérifiez `ca_bundle` si vous utilisez des URLs HTTPS.
  * En dev, vous pouvez commenter la clé `ca_bundle` pour voir si ça vient de là.

* **La page se charge mais les thumbnails restent sur les images originales**

  * Vérifiez que le serveur Python est lancé et joignable sur `thumb_base_url`.
  * Vérifiez que `thumb_status.php` répond bien (pas d’erreur PHP).
  * Ouvrez la console JS pour voir les logs `[THUMB]` (mettre `DEBUG_THUMBS = true` dans `thumb_polling.js`).

---

## 8. Résumé rapide (checklist)

1. **PHP**

   * Configurer `Config/dev.ini` (DB + `public_url` + éventuellement `ca_bundle`).
   * Vérifier PDO MySQL, cURL, mbstring.
   * Lancer : `php -S localhost:8000 index.php`.

2. **MySQL**

   * Créer la base.
   * Vérifier login/pwd.
   * Laisser `DatabaseInitializer` créer et remplir `PERSONNAGE`.

3. **Python (thumbnails)**

   * Récupérer les modèles (Git LFS ou téléchargements manuels dans `tools/vision/models`).
   * Dans `tools/vision`, lancer `python setup_env.py` (ou installer manuellement depuis `requirements.txt`).
   * Activer la venv, lancer `python thumb_server.py`.

4. **Navigateur**

   * Aller sur [http://localhost:8000](http://localhost:8000).
   * Vous devriez voir la collection, pouvoir **ajouter/éditer/supprimer** des personnages, et voir les thumbnails se générer si le serveur Python tourne.

```
```
