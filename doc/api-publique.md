# API publique (résumé)

| Élément | Type | Description |
|---------|------|-------------|
| `Bnine\FilesBundle\BnineFilesBundle` | class | Active le bundle |
| `Bnine\FilesBundle\Controller\FileController` | class | 11 endpoints HTTP (browse, gallery, upload, download, image, thumbnail, crop, delete, mkdir) |
| `Bnine\FilesBundle\Service\FileService` | class | Logique fichiers (list, upload, suppression, mkdir, ensureDirectory, deleteThumbs) |
| `Bnine\FilesBundle\Security\AbstractFileVoter` | abstract | Voter à implémenter côté hôte (3 méthodes : `canView`, `canEdit`, `canDelete`) |
| `Bnine\FilesBundle\Form\Type\IconUploadType` | form type | Champ avatar/icône avec modale (upload + crop optionnel) |
| `Bnine\FilesBundle\Form\Type\SelectFileType` | form type | Champ sélection image(s) depuis la gallery |
| `Bnine\FilesBundle\Twig\BnineFileExtension` | Twig ext | Fonction `bninefile()` |
| `Bnine\FilesBundle\Cache\CachePolicyInterface` | interface | Stratégie de cache HTTP pour `image()` / `thumbnail()` (voir [cache-policy.md](cache-policy.md)) |
| `Bnine\FilesBundle\Cache\DefaultCachePolicy` | class | Implémentation par défaut (30 jours, public) |
| `@BnineFilesBundle/file/_select.html.twig` | Twig include | Widget standalone (single/multiple, write/read) |
| `@BnineFilesBundle/Form/_theme.html.twig` | Twig form theme | Theme requis par `SelectFileType` |
| `@BnineFilesBundle/partials/_iframe_assets.html.twig` | Twig include | Assets pour iframes standalone (surchargeable par app hôte) |
| `bninefiles.js` | JS | Helpers modal `BnineModalOpen/Close` + widget `IconUploadType` (dispatch `bnine:modal:*`, `bnine:iconupload:change`) |
| `bninefiles-constants.js` | JS | Constantes `window.BnineEvents`, `window.bnineUuid()`, `window.bnineDispatch()` |
| Events JS `bnine:*` | DOM CustomEvent | 13 events (voir [custom-events.md](custom-events.md)) : `bnine:modal:open/close/closed`, `bnine:upload:done/error`, `bnine:crop:done/error`, `bnine:select:done`, `bnine:iconupload:change`, `bnine:selectfile:change`, `bnine:browse:delete/mkdir/refresh` |
| Préfixe routes | string | `/bninefiles` (11 routes) |
| Paramètres voter | `[domain, id]` | subject pour `view`/`edit`/`delete` |
| Domaines publics | `['avatar', 'logo', 'icon']` | renommage auto des fichiers uploadés + thumb |
| Modale JS | `BnineModalOpen(opts)` | Helper côté hôte |
| Sélection galerie | `addEventListener('bnine:select:done', cb)` | Callback côté hôte (remplace `window.bnineFileSelect` déprécié) |
| Callback upload | `addEventListener('bnine:upload:done', cb)` | Callback côté hôte (remplace `window.imageUploadDone` déprécié) |