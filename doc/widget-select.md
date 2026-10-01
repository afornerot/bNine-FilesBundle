# Widget Twig `@BnineFilesBundle/file/_select.html.twig`

Pour les cas où on veut intégrer le widget **hors Form Type Symfony** (formulaire HTML brut, UI 100% custom), le bundle expose un include Twig.

## Options (toutes)

| Option | Type | Défaut | Description |
|--------|------|--------|-------------|
| `mode` | string | `"single"` | `"single"` ou `"multiple"` |
| `access` | string | `"write"` | `"write"` ou `"read"` |
| `layout` | string | `"inline"` | `"inline"` ou `"column"` |
| `input_name` | string | auto (`bninefile[{rand}]`) | Nom HTML de l'input caché |
| `input_id` | string | auto depuis `input_name` + random | ID HTML de l'input caché |
| `domain` | string | `"uploads"` | Domaine de stockage |
| `entity_id` | int\|string | `0` | ID de l'entité propriétaire |
| `value` | string\|array | — | Valeur initiale (string en single, array de strings en multiple) |
| `label` | string | — | Libellé affiché au-dessus |
| `required` | bool | `false` | Ajoute `required` sur l'input caché |
| `show_name` | bool | `false` | Affiche le nom du fichier sous chaque vignette |
| `max_height` | int | `100` | Hauteur max de la preview (px) |
| `grid_cols` | int | `4` | Nb colonnes (mode multiple) |
| `placeholder` | string | auto | Texte du placeholder vide |

## Exemples

```twig
{# Single, write, inline #}
{% include '@BnineFilesBundle/file/_select.html.twig' with {
    mode: 'single',
    access: 'write',
    layout: 'inline',
    input_name: 'article[image]',
    domain: 'avatar',
    entity_id: user.id,
    value: article.image,
    label: 'Image de couverture',
    required: true,
    max_height: 200,
    show_name: true
} only %}

{# Single, read (lecture seule : modale sans upload) #}
{% include '@BnineFilesBundle/file/_select.html.twig' with {
    mode: 'single',
    access: 'read',
    input_name: 'article[preview]',
    domain: 'avatar',
    entity_id: user.id,
    value: article.image,
    label: 'Aperçu'
} only %}

{# Multiple, column, grille 6 colonnes #}
{% include '@BnineFilesBundle/file/_select.html.twig' with {
    mode: 'multiple',
    access: 'write',
    layout: 'column',
    input_name: 'gallery[images]',
    domain: 'avatar',
    entity_id: user.id,
    value: article.galleryImages,   # array de strings ou JSON string
    label: 'Galerie',
    grid_cols: 6,
    show_name: false
} only %}
```

## Comportement

- `single` : input contient un chemin relatif (`domain/id/path`)
- `multiple` : input contient un JSON array de strings
- **Additif** : on peut sélectionner en plusieurs fois, sans doublons
- **Auto-fermeture** de la modale après sélection
- **Multi-instance** : chaque widget a son propre `inputId`, pas d'interférence
- **Auto-suffisant** : reconstruit les URLs publiques via `/bninefiles/image/...` (pas besoin de passer les routes)