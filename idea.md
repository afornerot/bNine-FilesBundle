# Idées de fonctionnalités pour bNine-FilesBundle

Roadmap des évolutions futures du bundle. Ce fichier est un bac à sable :
les features y sont catégorisées par priorité et complexité.

## État actuel (v1.5)

**Capacités** :
- Upload (simple + crop)
- Browse / Gallery (liste + lightbox GLightbox)
- Sélection (single/multiple)
- Thumbnails auto
- Suppression + création de dossiers
- Sécurité par voter (`AbstractFileVoter`)
- Cache HTTP configurable (`CachePolicyInterface`)
- Stockage local + S3 via Flysystem
- 13 CustomEvents JS
- 11 routes HTTP
- 4 interfaces d'extension

**Métriques** :
- 9 fichiers PHP, 5 templates, 9 assets JS/CSS
- ~700 lignes dans `FileController`

---

## Priorité haute (bloquants / valeur forte, faisabilité élevée)

### 1. Événements PHP server-side (PostUploadEvent, PostDeleteEvent)
- **Description** : dispatcher des `Symfony\Contracts\EventDispatcher\Event` après upload/delete.
- Permet à l'app hôte de : logger, indexer en BDD, publier dans RabbitMQ, etc.
- **Pourquoi** : les CustomEvents JS ne couvrent que le client. Côté serveur, l'app doit pouvoir réagir.
- **Faisabilité** : moyenne. Ajouter `Symfony\Contracts\EventDispatcher\EventDispatcherInterface` au constructeur de `FileController`, créer les events, dispatcher à 2 endroits.
- **Statut** : déjà planifié dans la discussion, jamais implémenté.

### 2. Antivirus scan à l'upload
- **Description** : hook `PreUploadEvent` (cancellable) qui permet à l'app de scanner le fichier avant écriture.
- Permet intégration ClamAV, VirusTotal API, etc.
- **Pourquoi** : sécurité uploads. Beaucoup d'apps ont cette exigence.
- **Faisabilité** : moyenne. Event `PreUpload` cancellable.

### 3. Renommage automatique post-upload
- **Description** : hook `PostUploadEvent` qui peut modifier le nom du fichier.
- Use cases : slugify, UUID, hash, normalisation.
- **Pourquoi** : beaucoup d'apps ont des règles de nommage strictes.
- **Faisabilité** : facile (mutate `originalName` dans l'event).

### 4. Webhooks
- **Description** : POST sortant vers une URL configurée après upload/delete.
- Utile pour architectures event-driven / micro-services.
- **Pourquoi** : permet l'intégration sans couplage fort au code PHP.
- **Faisabilité** : moyenne. Nouvelle interface `WebhookDispatcherInterface`.

---

## Priorité moyenne (UX, valeur forte, faisabilité moyenne)

### 5. Filtres sur browse/gallery
- **Description** : filtrer par extension (`.jpg`, `.pdf`, etc.), par date, par taille.
- UI : dropdown dans le template browse/gallery.
- **Pourquoi** : dès qu'on a beaucoup de fichiers (>50), c'est indispensable.
- **Faisabilité** : facile. Modifier `FileService::list()` pour accepter des filtres, modifier le template.

### 6. Recherche full-text dans les noms
- **Description** : query `?q=keyword` filtre la liste par nom.
- **Pourquoi** : très demandé, simple à implémenter.
- **Faisabilité** : facile. LIKE dans `FileService::list()`.

### 7. Tri
- **Description** : `?sort=name|date|size&order=asc|desc`.
- **Pourquoi** : standard UX.
- **Faisabilité** : facile. Ajouter `usort()` ou SQL ORDER BY.

### 8. Pagination
- **Description** : si >N fichiers par dossier, paginer (prev/next).
- **Pourquoi** : indispensable pour dossiers volumineux.
- **Faisabilité** : moyenne. Nouvelle route + param `?page=`, UI simple.

### 9. Bulk operations
- **Description** : sélectionner plusieurs fichiers + delete/move/download en masse.
- UI : checkboxes sur chaque ligne.
- **Pourquoi** : gain de temps utilisateur.
- **Faisabilité** : moyenne. Nouvelle route POST avec array de paths.

### 10. Téléchargement multiple / ZIP
- **Description** : bouton "Télécharger tout" qui génère un ZIP à la volée.
- **Pourquoi** : cas d'usage archivage / partage.
- **Faisabilité** : moyenne. Nouvelle route, lib `ZipStream`.

---

## Priorité basse (nice-to-have, faisabilité variable)

### 11. Versioning
- **Description** : garder l'historique des anciennes versions. Schéma `path/v1/file.jpg`, `path/v2/file.jpg`.
- **Faisabilité** : moyenne à élevée. Modifier `FileService::delete()`, ajouter versioning read.

### 12. Partage public via lien signé
- **Description** : URL avec token temporaire pour partager un fichier sans voter.
- **Pourquoi** : partage externe sans compte.
- **Faisabilité** : facile. Symfony `UriSigner` + nouvelle route.

### 13. Watermark automatique
- **Description** : appliquer une image watermark sur les JPG uploadés.
- **Pourquoi** : protection copyright pour les photos.
- **Faisabilité** : moyenne. Imagine + configuration par domain.

### 14. Quota par utilisateur/domain
- **Description** : limiter la taille totale stockée par domain.
- Vérification avant upload, message d'erreur si dépassé.
- **Faisabilité** : facile. Vérifier `disk_total_size` dans `FileService`.

### 15. Drag & drop pour réorganiser
- **Description** : drag un fichier d'un dossier à un autre dans le browser.
- **Faisabilité** : moyenne. JS + nouvelle route `POST /move`.

### 16. Prévisualisation PDF / vidéo / audio
- **Description** : actuellement seuls les images ont un thumbnail.
- PDF : première page via Imagick/ghostscript.
- Vidéo : poster via ffmpeg.
- Audio : icône + métadonnées (durée, bitrate).
- **Faisabilité** : moyenne. Dépendances natives.

### 17. OCR / extraction EXIF
- **Description** : extraire les EXIF (géolocalisation, date, appareil) à l'upload image.
- Event `PostUploadEvent` hookable.
- **Faisabilité** : moyenne. `exiftool` natif ou lib PHP.

### 18. Support S3-compatible plus large
- **Description** : déjà supporté via Flysystem, juste mieux documenter (MinIO, OVH, Scaleway).
- **Faisabilité** : facile (doc only).

### 19. Tests automatisés
- **Description** : actuellement ZÉRO tests.
- PHPUnit sur controllers, services, events, validators.
- **Pourquoi** : CRITIQUE pour la stabilité d'un bundle.
- **Faisabilité** : moyenne à élevée. Mais critique avant v2.0.

### 20. Documentation API OpenAPI
- **Description** : générer le schéma OpenAPI depuis les routes/controllers.
- **Pourquoi** : facilite l'intégration par les apps tierces.
- **Faisabilité** : moyenne. `nelmio/api-doc-bundle` ou manuel.

---

## Idées "out-of-the-box"

### 21. Compression auto des uploads
- **Description** : si image > 2MB, proposer de compresser via Imagine.
- **Faisabilité** : facile. Hook PostUpload.

### 22. Génération de variants (responsive images)
- **Description** : à l'upload image, générer plusieurs tailles (320, 640, 1024, 1920).
- Routes `?w=320` pour servir.
- **Pourquoi** : performance web moderne (srcset).
- **Faisabilité** : moyenne. Imagine + nouveau service.

### 23. Sync avec cloud storage
- **Description** : Dropbox, Google Drive, OneDrive via Flysystem adapters.
- **Hors scope bundle Symfony pur**, mais faisable.
- **Faisabilité** : élevée.

### 24. AI tagging
- **Description** : à l'upload, API externes (AWS Rekognition, Google Vision) pour tagger automatiquement.
- Event `PostUploadEvent` hookable.
- **Faisabilité** : moyenne.

### 25. Soft delete / trash
- **Description** : au lieu de supprimer définitivement, déplacer dans `_trash/`.
- UI corbeille + restore.
- **Pourquoi** : permet undo pour les erreurs.
- **Faisabilité** : moyenne.

---

## Idées "killer features"

### 26. Synchronisation multi-device
- WebSocket pour voir les changements en temps réel (autres users).
- **Faisabilité** : élevée (nécessite Mercure ou WS).

### 27. Versioning Git-like
- Chaque fichier versionné comme un commit, avec diff visuel.
- **Faisabilité** : très élevée. Trop ambitieux.

### 28. Édition collaborative en temps réel
- Plusieurs users éditent le même fichier simultanément.
- **Faisabilité** : hors scope (besoin d'CRDT).

---

## Notes

- Ce fichier n'est **pas** dans la doc utilisateur du bundle. C'est un TODO interne.
- À chaque release majeure, déplacer les features terminées dans `CHANGELOG.md`.
- Les estimations de faisabilité sont indicatives.
- Priorités à réviser à chaque release en fonction des retours utilisateurs.

## Légende

- **Faisabilité facile** : < 1 jour
- **Faisabilité moyenne** : 1-3 jours
- **Faisabilité élevée** : > 3 jours