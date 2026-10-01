# Stockage (local / S3)

## Mode local

Arborescence sur disque (`{uploads}` = `kernel.project_dir/uploads` par défaut) :

```
uploads/
└── {domain}/
    └── {id}/
        ├── fichier.jpg
        ├── sous-dossier/
        │   └── autre.png
        └── _thumbs/
            ├── 300xN/
            │   └── fichier.jpg     # miniature auto (mini 300px côté le plus petit)
            └── 150x150/
                └── fichier.jpg     # miniature crop (150x150 par défaut)
```

Les dossiers dont le nom commence par `_` (notamment `_thumbs`) sont exclus de la navigation et des listings.

## Mode S3

Tout fonctionne via Flysystem. Le bucket doit être configuré avec une clé d'accès.

Variables :

```env
STORAGE_S3=1
S3_ENDPOINT=http://minio:9000
S3_BUCKET=my-uploads
S3_ACCESS_KEY=changeme
S3_SECRET_KEY=changeme
S3_REGION=us-east-1
```

Le bundle gère :

- Création de dossiers (récursivement, segment par segment)
- Suppression de fichiers / dossiers
- Stream upload (zéro copie locale si S3)
- Listing via `listContents`

## Thumbnails

Générés via `imagine/imagine` (avec GD ou Imagick) :

- **Thumbnail auto** : route `bninefiles_files_thumbnail` — redimensionne pour que la plus petite dimension soit ≥ 300px (en conservant le ratio).
- **Thumbnail crop** : route `bninefiles_files_crop` — crop à la sélection utilisateur, redimensionné à la taille demandée (`min_size` par défaut 150).

Cache navigateur `Cache-Control: public, max-age=2592000` (30 jours) sur les routes `image` et `thumbnail`.

La politique de cache est surchargeable via `CachePolicyInterface` — voir [cache-policy.md](cache-policy.md).