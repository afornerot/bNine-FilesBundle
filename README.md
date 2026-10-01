# bNine-FilesBundle

Bundle Symfony pour gérer des fichiers attachés à des entités : **upload via modale**, **navigateur de fichiers**, **galerie d'images**, **recadrage (crop)**, **miniatures (thumbnails)**, **téléchargement**, **sécurité par voter**, support **local** et **S3** via Flysystem.

Toutes les routes sont préfixées par `/bninefiles`.

## Installation rapide

```bash
composer require bnine/filesbundle
```

Puis : activer le bundle, charger les routes, déclarer le `FileService` (Flysystem) et implémenter un voter. Détails complets dans [doc/installation.md](doc/installation.md).

## Documentation complète

La documentation détaillée est dans [`doc/`](doc/README.md).

- [doc/prerequis.md](doc/prerequis.md) — Dépendances, bundles et libs tierces
- [doc/installation.md](doc/installation.md) — Mise en place pas-à-pas
- [doc/concepts.md](doc/concepts.md) — Domaines, ids, paths, modes
- [doc/routes.md](doc/routes.md) — Liste des 11 routes HTTP
- [doc/quickstart.md](doc/quickstart.md) — Navigateur de fichiers en 2 lignes
- [doc/voter.md](doc/voter.md) — Voter de sécurité (`view`/`edit`/`delete`)
- [doc/modale.md](doc/modale.md) — API JS `BnineModalOpen/Close`
- [doc/gallery-select.md](doc/gallery-select.md) — Modes single / multiple / lightbox
- [doc/form-types.md](doc/form-types.md) — `IconUploadType`, `SelectFileType`
- [doc/widget-select.md](doc/widget-select.md) — Include Twig standalone `_select.html.twig`
- [doc/twig.md](doc/twig.md) — Extension Twig `bninefile()`
- [doc/file-service.md](doc/file-service.md) — Service `FileService` (bas niveau)
- [doc/stockage.md](doc/stockage.md) — Stockage local / S3 / thumbnails
- [doc/sample.md](doc/sample.md) — Sample Symfony 7 (Docker, port 9002)
- [doc/depannage.md](doc/depannage.md) — Problèmes fréquents
- [doc/api-publique.md](doc/api-publique.md) — Résumé de l'API publique
- [doc/cache-policy.md](doc/cache-policy.md) — `CachePolicyInterface` (HTTP cache)
- [doc/custom-events.md](doc/custom-events.md) — CustomEvents JS `bnine:*`
- [doc/firewall-cache.md](doc/firewall-cache.md) — Config firewall pour cache navigateur

## Licence

MIT.