# Galerie + sélection single/multiple

La galerie peut être ouverte en mode **sélection** depuis n'importe quelle page.

## Mode single — sélection immédiate

URL : `/bninefiles/gallery/{domain}/{id}/0?select=onSelectSingle`

Code JS côté page hôte :

```javascript
window.bnineFileSelect = function (data) {
    // data = { url: string, path: string, name: string }
    console.log('Image choisie :', data);
};
```

Le clic sur une image appelle `bnineFileSelect(data)`, puis ferme la modale.

## Mode multiple — validation explicite

URL : `/bninefiles/gallery/{domain}/{id}/0?select=multiple`

Un bouton **Valider (N)** apparaît en bas (sticky). Le clic appelle :

```javascript
window.bnineFileSelect = function (items) {
    // items = [ { url, path, name }, ... ]
    console.log('Images choisies :', items);
};
```

## Mode normal — lightbox seule

Sans `?select=...`, la galerie est en lecture seule avec lightbox GLightbox (loop + touchNavigation + preload).

## Exemple complet

```twig
<a href="#" onclick="openGallery(); return false;">Choisir une image</a>

<div id="result"></div>

<script>
function openGallery() {
    BnineModalOpen({
        id: 'select-gallery',
        title: 'Choisir une image',
        url: '/bninefiles/gallery/blog/42/0?select=onSelectSingle',
        height: '700px'
    });
}

window.bnineFileSelect = function (data) {
    document.getElementById('result').innerHTML =
        '<img src="' + data.url + '" alt=""><br><code>' + data.path + '</code>';
};
</script>
```

> Pour un usage avec persistance du résultat dans un `<input>`, utilisez plutôt `SelectFileType` (voir [form-types.md](form-types.md#selectfiletype--form-type)) ou le widget `_select.html.twig` (voir [widget-select.md](widget-select.md)) — ils gèrent le multi-instance et le additif automatiquement.