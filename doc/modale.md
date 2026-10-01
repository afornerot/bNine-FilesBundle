# Système de modale (JS)

`bninefiles.js` expose une modale overlay légère (z-index 9999) qui charge une page dans un iframe.

## API

```javascript
// Ouvrir une modale
BnineModalOpen({
    id: 'ma-modale',            // ID unique (string) — obligatoire
    title: 'Titre affiché',     // string
    url: '/bninefiles/uploadmodal/blog/42',  // URL chargée dans l'iframe — obligatoire
    height: '600px',            // Hauteur iframe (défaut : '600px')
    currentInput: 'id_input',   // (optionnel) ID du champ caché à mettre à jour
    onClose: function () {      // Callback appelé à la fermeture
        // ...
    }
});

// Fermer depuis l'iframe (ex: bouton Annuler)
window.parent.BnineModalClose();

// Callback après upload (appelé par le contenu de l'iframe après upload + crop éventuel)
window.parent.imageUploadDone('avatar/0/_thumbs/abc123.jpg', 'http://app/bninefiles/image/avatar/0?path=_thumbs/abc123.jpg');

// Fermer programmatiquement
BnineModalClose();
```

## Événements

Un événement global `bnine-modal-closed` est dispatché à la fermeture sur `document`. Utile pour recharger une galerie :

```javascript
document.addEventListener('bnine-modal-closed', function () {
    // Rafraîchir la galerie parente
});
```

> Pour la liste complète et à jour des CustomEvents émis par le bundle (y compris `bnine:modal:*`, `bnine:upload:*`, `bnine:select:*`, etc.), voir [custom-events.md](custom-events.md).