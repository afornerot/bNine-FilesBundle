# Concepts clés

| Concept | Description |
|---------|-------------|
| **`domain`** | Segment logique d'organisation (ex: `avatar`, `blog`, `gallery`, `user-document`). Sous-dossier racine du stockage. |
| **`id`** | Identifiant entier (ou string) de l'entité à laquelle les fichiers sont rattachés. Sous-dossier enfant. |
| **`path`** | Chemin relatif **à l'intérieur** de `{uploads}/{domain}/{id}/`. Ex: `photos/2024/photo.jpg`. |
| **`editable`** | `1` = mode écriture (upload, dossiers, suppression), `0` = lecture seule (navigation, téléchargement, lightbox). |
| **Domaine public** | `avatar`, `logo`, `icon` : le bundle renomme les fichiers uploadés en `bin2hex(random_bytes(16)).{ext}` et crée automatiquement un thumb (copie). |

## Arborescence type (mode local)

```
{uploads}/
├── {domain}/
│   └── {id}/
│       ├── fichier.jpg
│       ├── sous-dossier/
│       │   └── autre.png
│       └── _thumbs/
│           ├── fichier.jpg        # thumb auto (≥300px côté le plus petit)
│           └── 150x150/
│               └── fichier.jpg    # thumb crop (150x150 par défaut)
```

## Préfixe des routes

Toutes les routes du bundle commencent par `/bninefiles` :

```
http://app/bninefiles/list/blog/42/1      # browse éditable pour l'entité blog #42
http://app/bninefiles/gallery/avatar/7/0  # galerie en lecture seule pour l'avatar de l'entité #7
```