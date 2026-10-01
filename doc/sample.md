# Sample de démonstration

Un sample Symfony 7 complet est disponible dans `sample/`. Il démarre via Docker Compose sur le port **9002** :

```bash
cd sample
docker compose up -d
# Ouvrir http://localhost:9002
```

Le sample démontre **toutes les fonctionnalités du bundle** avec, pour chaque page, le code d'usage complet (contrôleur, Form Type, Twig, options) :

| URL | Démontre |
|-----|----------|
| `/` | Menu d'accueil |
| `/browse` | Navigateur de fichiers : upload, dossiers, suppression |
| `/browse?editable=1` | Browse en **mode écriture** |
| `/browse?editable=0` | Browse en **mode lecture** |
| `/gallery` | Galerie avec lightbox GLightbox |
| `/gallery?editable=1` | Galerie en **mode écriture** |
| `/gallery?editable=0` | Galerie en **mode lecture** |
| `/icon` | Icônes HTML avec modale d'upload + recadrage (5 variantes) |
| `/form-icon` | Form Symfony avec `IconUploadType` (5 champs) |
| `/form-demo` | Form Symfony avec `SelectFileType` (image + galerie) |
| `/select-demo-single` | Widget `_select.html.twig` en mode single (3 démos) |
| `/select-demo-multiple` | Widget `_select.html.twig` en mode multiple (3 démos) |

Voir [sample/README.md](../sample/README.md) pour les détails Docker.