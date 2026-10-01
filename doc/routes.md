# Routes

| URL | Méthode | Nom | Voter | Description |
|-----|---------|-----|-------|-------------|
| `/bninefiles/list/{domain}/{id}/{editable}` | GET | `bninefiles_files` | `view`/`edit` | Navigateur de fichiers (liste + actions) |
| `/bninefiles/uploadmodal/{domain}/{id}?path=&imageOnly=&crop=&min_size=&ratio=&configurable=` | GET | `bninefiles_files_uploadmodal` | `edit` | Page d'upload (iframe modale) |
| `/bninefiles/uploadfile?domain=&id=&path=` | POST (multipart) | `bninefiles_files_uploadfile` | `edit` | Upload du fichier (appelé par Dropzone) |
| `/bninefiles/delete/{domain}/{id}` | POST (JSON `{path}`) | `bninefiles_files_delete` | `delete` | Suppression fichier/dossier (+ nettoyage des thumbs) |
| `/bninefiles/mkdir/{domain}/{id}` | POST (form `{path, name}`) | `bninefiles_files_mkdir` | `edit` | Création de sous-dossier |
| `/bninefiles/download/{domain}/{id}?path=` | GET | `bninefiles_files_download` | `view` | Téléchargement (attachment) |
| `/bninefiles/image/{domain}/{id}?path=` | GET | `bninefiles_files_image` | `view` | Affichage inline (Content-Disposition: inline) |
| `/bninefiles/thumbnail/{domain}/{id}?path=` | GET | `bninefiles_files_thumbnail` | `view` | Miniature auto-générée (≥300px côté le plus petit) |
| `/bninefiles/gallery/{domain}/{id}/{editable}?select=&path=&compact=` | GET | `bninefiles_files_gallery` | `view`/`edit` | Galerie d'images (grille + lightbox ou modale sélection) |
| `/bninefiles/crop-page/{domain}/{id}?path=&min_size=&ratio=&configurable=` | GET | `bninefiles_files_crop_page` | `edit` | Page de recadrage (iframe modale) |
| `/bninefiles/crop/{domain}/{id}` | POST | `bninefiles_files_crop` | `edit` | Application du crop (génère thumbnail `{size}x{size}`, défaut 150) |

## Notes

- `editable` ∈ `{0, 1}`. Si absent, défaut = `1`.
- `path` (query string) = sous-dossier courant dans `{domain}/{id}/`.
- `select` (gallery) ∈ `{'onSelectSingle', 'multiple'}` ou vide (lightbox seule).
- `compact` (list/gallery) ∈ `{0, 1}`. Si `1`, retire les wrappers `card`/`header` pour intégration dans une autre UI.
- `imageOnly` (uploadmodal) ∈ `{0, 1}`. Si `1`, filtre les extensions aux formats image.
- `crop` / `min_size` / `ratio` / `configurable` (uploadmodal, crop-page) : reportés en query string depuis `IconUploadType` (voir [form-types.md](form-types.md#iconuploadtype--form-type)) ou construits manuellement.