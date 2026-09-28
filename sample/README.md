# bNine-FilesBundle — Sample

Application Symfony 7 minimale démontrant chaque fonctionnalité du bundle `bnine/filesbundle`.

## 🚀 Démarrage rapide (Docker)

```bash
cd sample/
docker compose up -d --build
# Attendre ~2 minutes (premier build uniquement)
# Ouvrir http://localhost:9002
```

L'application démarre sur le port **9002** et est accessible immédiatement (mode démo sans authentification).

## ⚡ Workflow de développement

L'image Docker est organisée en **2 stages** avec cache séparé :

| Stage | Contenu | Quand rebuild |
|-------|---------|---------------|
| `base` | PHP 8.2 + Apache + libs système | Rarement (changement PHP version, nouvelles extensions) |
| `vendor` | Composer install de Symfony 7.1 + bundle | Quand `composer.json` change |
| `app` | Code source + assemble | Premier build, ou changement de structure |

Les modifications de code ne nécessitent **PAS de rebuild image** grâce aux bind-mounts :

```bash
# Modifier src/, templates/, config/ du sample → live instantané (juste cache:clear)
docker compose exec app php bin/console cache:clear

# Modifier src/, templates/, config/, Resources/ du bundle → restart suffit
docker compose restart app

# Modifier une dépendance Composer → rebuild stage vendor
docker compose build --no-cache vendor
```

> **Note** : les libs tierces (jQuery, Bootstrap, FontAwesome, Dropzone, Cropper, GLightbox) sont **embarquées dans le bundle** (`vendor/bnine/filesbundle/Resources/public/lib/`) et publiées via `assets:install` dans `public/bundles/bninefiles/lib/`. Plus besoin de stage dédié ni de CDN.

Tout est dans `app_uploads` et `app_var` (volumes Docker) → aucune permission à gérer.

## 📋 Pages de démo

| URL | Démontre |
|-----|----------|
| `/` | Menu d'accueil |
| `/browse` | Navigateur de fichiers : upload, dossiers, suppression |
| `/browse?editable=1` | Browse en **mode écriture** (upload + dossiers + suppression) |
| `/browse?editable=0` | Browse en **mode lecture** (consultation + téléchargement uniquement) |
| `/gallery` | Galerie avec lightbox GLightbox |
| `/gallery?editable=1` | Galerie en **mode écriture** |
| `/gallery?editable=0` | Galerie en **mode lecture** |
| `/icon` | Icônes HTML (5 démos : 4 avec crop + 1 sans crop) |
| `/form-icon` | Form Symfony utilisant `IconUploadType` (5 champs) |
| `/form-demo` | Form Symfony utilisant `SelectFileType` (image + galerie) |
| `/select-demo-single` | Widget `SelectFileType` en mode single (3 démos : write/column/read) |
| `/select-demo-multiple` | Widget `SelectFileType` en mode multiple (3 démos : write/column/read) |
| `/cache-policy` | Démontre `CachePolicyInterface` (max-age, public/private par fichier) |
| `/readme` | README du bundle formaté en HTML (Markdown parser) |

Chaque page Browse et Gallery affiche deux onglets pour basculer entre **mode écriture** et **mode lecture**. Le mode lecture masque les boutons Upload, création de dossier et suppression ; seul le téléchargement reste accessible.

## 🧪 Tester le flux complet

### 1. Upload
- Aller sur `/browse`
- Cliquer sur **Upload** → glisser-déposer un fichier
- Le fichier apparaît dans la liste

### 2. Navigation
- Créer un dossier avec le bouton **Dossier**
- Cliquer sur un dossier pour entrer dedans
- Cliquer sur 🏠 pour revenir à la racine

### 3. Galerie + sélection
- Aller sur `/gallery`
- Uploader plusieurs images
- Cliquer sur une image → lightbox GLightbox
- Aller sur `/select-target` → **Choisir une image** (single)
- Ou → **Choisir plusieurs images** (multiple avec checkbox)

### 4. Crop
- Aller sur `/icon`
- Cliquer sur **Modifier** → uploader une image
- Sélectionner une zone carrée → **Valider**
- Le thumbnail `150x150` est généré

### 5. Suppression
- Sur `/browse` : hover sur un fichier → bouton 🗑️ rouge
- Confirmer la suppression

## 🔍 Endpoints internes du bundle

Tous les endpoints sont montés sur `/bninefiles/...`. Tester directement :

```bash
# Lister les fichiers d'une entité
curl http://localhost:9002/bninefiles/list/sample/1/1

# Afficher une image (si déjà uploadée)
curl 'http://localhost:9002/bninefiles/image/sample/1?path=image.jpg'

# Thumbnail auto-généré
curl 'http://localhost:9002/bninefiles/thumbnail/sample/1?path=image.jpg'
```

## ⚙️ Configuration

Fichier `.env` :

```env
APP_ENV=dev
APP_DEBUG=1
STORAGE_DSN=local://uploads
STORAGE_S3=0
```

Le sample fonctionne **uniquement en mode local** (pas de S3).

Le voter `App\Security\FileVoter` autorise tout en mode démo. En production, restreignez par utilisateur/rôle/domain.

## 🏗️ Architecture du sample

```
sample/
├── docker-compose.yml          # Volumes nommés (app_uploads, app_var) + bind-mounts code
├── docker/
│   ├── Dockerfile              # 3 stages : base / vendor / app
│   ├── apache/000-default.conf # FallbackResource index.php
│   ├── entrypoint.sh           # init : dump-autoload + cache:clear + seed
│   └── seed.sh                 # Génère 12 images sample + 4 avatars par défaut (GD)
├── composer.json               # Dépendances Symfony 7.1 + bundle
├── .env                        # Config locale
├── bin/console                 # CLI Symfony
├── public/
│   ├── index.php               # Front controller
│   └── bundles/                # Assets bundle publiés via assets:install (bninefiles.js/css + libs tierces)
├── config/
│   ├── bundles.php
│   ├── routes.yaml
│   ├── services.yaml
│   └── packages/{framework,twig,flysystem,security}.yaml
├── src/
│   ├── Kernel.php
│   ├── Controller/
│   │   ├── DemoController.php       # Pages statiques (browse, gallery, icon, select)
│   │   ├── DemoFormController.php   # Form Symfony SelectFileType
│   │   └── DemoIconController.php   # Form Symfony IconUploadType
│   ├── Form/
│   │   ├── DemoArticleType.php
│   │   ├── DemoIconType.php
│   │   └── Dto/{DemoArticleDto, DemoIconDto}.php
│   └── Security/FileVoter.php
└── templates/
    ├── base.html.twig          # Layout avec libs locales
    └── demo/
        ├── index.html.twig            # Menu accueil
        ├── browse.html.twig           # Navigateur (file browser)
        ├── gallery.html.twig          # Galerie (avec lightbox)
        ├── icon.html.twig             # Icones HTML (5 variantes crop)
        ├── form_demo.html.twig        # Form Symfony avec SelectFileType
        ├── form_demo_result.html.twig # Resultat soumission form_demo
        ├── form_icon.html.twig        # Form Symfony avec IconUploadType
        ├── form_icon_result.html.twig # Resultat soumission form_icon
        ├── select_demo_single.html.twig    # Widget SelectFileType single
        └── select_demo_multiple.html.twig  # Widget SelectFileType multiple
```

Au premier démarrage, `seed.sh` génère automatiquement 12 images placeholder dans `uploads/sample/1/` et 4 dans `uploads/avatar/0/` pour que la démo soit immédiatement utilisable.

## 🔧 Commandes utiles

```bash
# Voir les logs
docker compose logs -f app

# Entrer dans le container
docker compose exec app bash

# Vider le cache après modif template
docker compose exec app php bin/console cache:clear

# Redémarrer (après modif du bundle)
docker compose restart app

# Rebuild après changement composer.json
docker compose build --no-cache vendor

# Tout reset (perte des fichiers uploadés)
docker compose down -v && docker compose up -d --build
```

## 📚 Documentation

Voir le [README principal](../README.md) pour la doc complète du bundle.
