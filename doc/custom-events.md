# CustomEvents JS (extension côté navigateur)

Le bundle dispatche des **CustomEvents DOM standard** à chaque action notable (modale, upload, crop, sélection, suppression). Les apps hotes s'abonnent via `addEventListener` :

```js
document.addEventListener('bnine:upload:done', function (e) {
    console.log('Fichier uploadé :', e.detail.filename);
    // Rafraichir la liste, mettre a jour la BDD, notifier, etc.
});
```

## Liste des events

| Event | Quand | `e.detail` |
|-------|-------|------------|
| `bnine:modal:open` | Une modale s'ouvre | `{id, title, url, height, currentInput, origin, endpoint}` |
| `bnine:modal:close` | Une modale commence à se fermer | `{id, origin}` |
| `bnine:modal:closed` | Une modale est complètement fermée | `{id, origin}` |
| `bnine:upload:done` | Un fichier est uploadé avec succès | `{domain, id, path, filename, url, size, mimeType, originalName, origin, endpoint}` |
| `bnine:upload:error` | Erreur d'upload (Dropzone error) | `{message, errors, origin, endpoint}` |
| `bnine:crop:done` | Crop terminé avec succès | `{domain, id, path, url, origin}` |
| `bnine:crop:error` | Erreur de crop | `{message, origin}` |
| `bnine:select:done` | Sélection dans gallery | `{items: [{url, path, name}], mode: 'single'\|'multiple', inputId, origin}` |
| `bnine:iconupload:change` | Valeur d'un IconUploadType change | `{value, inputId, origin}` |
| `bnine:selectfile:change` | Valeur d'un SelectFileType change (y compris clear) | `{value, mode, inputId, origin, cleared?}` |
| `bnine:browse:delete` | Fichier/dossier supprimé | `{path, isDirectory: false, origin}` |
| `bnine:browse:mkdir` | Dossier créé | `{path, name, origin}` |
| `bnine:browse:refresh` | Liste browse/gallery rafraîchie | `{path, origin}` |

## `origin` (UUID unique par widget)

Chaque widget (IconUploadType, SelectFileType, gallery) génère un **UUID v4 unique** au moment de son initialisation. Cet UUID est propagé :

- côté JS : `data-instance` sur le DOM, query string `?origin=<uuid>` dans les URLs d'iframe
- côté serveur : passé dans l'event comme `origin`

Cela permet de distinguer 2 galleries sur la même page (rare mais possible).

## Exemples d'utilisation

**Logger tous les uploads** (debug) :

```js
document.addEventListener('bnine:upload:done', function (e) {
    console.log('Upload :', e.detail);
});
```

**Rafraîchir une liste après suppression** :

```js
document.addEventListener('bnine:browse:delete', function (e) {
    document.querySelector('#my-list').dataset.lastDeletedPath = e.detail.path;
    // declencher votre propre refresh AJAX
});
```

**Tracker analytics** :

```js
document.addEventListener('bnine:select:done', function (e) {
    if (typeof gtag === 'function') {
        gtag('event', 'file_selected', {
            mode: e.detail.mode,
            count: e.detail.items.length,
            widget: e.detail.origin,
        });
    }
});
```

**Ouvrir une modale custom au lieu du bundle** :

```js
document.addEventListener('bnine:modal:open', function (e) {
    if (e.detail.endpoint === 'iconupload' && myCondition) {
        e.preventDefault(); // annule la modale bundle
        openMyCustomModal();
    }
});
```

## BREAKING CHANGE v1.4.5+ : suppression des callbacks globaux

Avant cette version, l'app hôte pouvait utiliser :

```js
// DEPRECIE (supprime)
window.bnineFileSelect = function(data) { /* ... */ };
window.imageUploadDone = function(filepath, fileUrl) { /* ... */ };
```

**Ces callbacks sont supprimés.** Utilisez les CustomEvents `bnine:select:done` et `bnine:upload:done` à la place.

Migration rapide :

```js
// AVANT
window.bnineFileSelect = function(data) {
    console.log(data);
};

// APRES
document.addEventListener('bnine:select:done', function(e) {
    console.log(e.detail.items);
});

// AVANT
window.imageUploadDone = function(filepath, fileUrl) {
    console.log(filepath, fileUrl);
};

// APRES
document.addEventListener('bnine:upload:done', function(e) {
    console.log(e.detail.filename, e.detail.url);
});
```

Les helpers `BnineModalOpen()` et `BnineModalClose()` restent disponibles (utilitaires, pas des callbacks).

## Constantes JS

Les noms d'events sont exposés via `window.BnineEvents` (dans `bninefiles-constants.js`) :

```js
document.addEventListener(BnineEvents.UPLOAD_DONE, function(e) {
    // ...
});
```

Helper UUID : `window.bnineUuid()` retourne un UUID v4.

Helper dispatch (utilisé en interne) : `window.bnineDispatch(name, detail, target)` dispatch un CustomEvent avec fallback IE/old Edge.