# Form Types

Deux Form Types sont fournis par le bundle :

- [`IconUploadType`](#iconuploadtype--form-type) — champ « upload via modale » avec preview, recadrage optionnel.
- [`SelectFileType`](#selectfiletype--form-type) — champ « sélection d'image(s) existante(s) » via la gallery.

---

## `IconUploadType` — Form Type

Champ caché qui ouvre une modale d'upload, affiche une preview, supporte le recadrage optionnel.

### Options

| Option | Type | Défaut | Description |
|--------|------|--------|-------------|
| `label` | string | — | Label Symfony standard |
| `icon_endpoint` | string | `"icon"` | Domaine logique (utilisé par les hooks applicatifs) |
| `icon_label` | string | `"Icon"` | Titre de la modale d'upload + texte du bouton |
| `icon_empty_preview` | string | `"medias/icon/icon_pin.png"` | URL d'une preview quand l'input est vide |
| `icon_domain` | string | `"avatar"` | **Domaine de stockage** (sous-dossier `uploads/`) |
| `icon_entity_id` | int\|string | `0` | **ID de l'entité propriétaire** |
| `crop` | bool | `false` | Active le recadrage après upload |
| `crop_min_size` | int | `300` | Taille minimale finale (largeur ET hauteur), bornes 50–2000 |
| `crop_ratio` | string | `"1/1"` | Ratio largeur/hauteur (`"1/1"`, `"16/9"`, `"4/3"`, `"free"`, …) |
| `crop_configurable` | bool | `false` | `true` = champs `min_size` et `ratio` éditables dans la modale crop |
| `preview_max_height` | int | `100` | Hauteur max (px) de la preview dans le widget parent |
| `img_class` | string | `""` | Classes CSS supplémentaires ajoutées à la balise `<img>` de la preview. Les classes par défaut (`mb-2 icon-upload-preview`) restent présentes sauf si `img_class_replace=true`. |
| `img_class_replace` | bool | `false` | `true` = remplace **toutes** les classes par défaut de l'`<img>` par `img_class`. Utile pour les styles très spécifiques. |

> `icon_domain` et `icon_entity_id` sont validés en type par `setAllowedTypes` (string / int|string). Le router est injecté via `setRouter()` (autoconfiguré dans `config/services.yaml` du bundle).

### Exemple Form Type

```php
use Bnine\FilesBundle\Form\Type\IconUploadType;

$builder
    // Avatar carré 300x300, non configurable, preview 100px
    ->add('avatar', IconUploadType::class, [
        'label'              => 'Avatar',
        'icon_label'         => 'Avatar',
        'icon_empty_preview' => '',
        'icon_domain'        => 'avatar',
        'icon_entity_id'     => $user->getId(),
        'crop'               => true,
        'crop_min_size'      => 300,
        'crop_ratio'         => '1/1',
        'crop_configurable'  => false,
        'preview_max_height' => 100,
    ])
    // Bannière 16/9 configurable, preview 250px
    ->add('banner', IconUploadType::class, [
        'label'              => 'Bannière',
        'icon_label'         => 'Bannière',
        'icon_domain'        => 'avatar',
        'icon_entity_id'     => $user->getId(),
        'crop'               => true,
        'crop_min_size'      => 400,
        'crop_ratio'         => '16/9',
        'crop_configurable'  => true,
        'preview_max_height' => 250,
    ])
    // Upload simple SANS crop : la modale affiche uniquement DropzoneJS,
    // pas de CropperJS. Pour les domaines publics (avatar/logo/icon), un
    // thumb (copie) est créé automatiquement et le path retourné est
    // préfixé par `_thumbs/`.
    ->add('attachment', IconUploadType::class, [
        'label'              => 'Pièce jointe',
        'icon_label'         => 'Pièce jointe',
        'icon_domain'        => 'blog',
        'icon_entity_id'     => $article->getId(),
        'crop'               => false,
        'preview_max_height' => 150,
    ]);
```

### Rendu Twig

```twig
{{ form_label(form.avatar) }}
{{ form_widget(form.avatar) }}
{# Le thème du bundle (_theme.html.twig) intercepte le rendu du champ via
   le préfixe 'icon_upload' et délègue à icon_upload.html.twig, qui produit
   preview + bouton "Modifier" + input caché en HTML statique.
   L'URL de la preview passe par la fonction Twig bninefile() (cf. twig.md)
   pour ajouter le '/' de manière cohérente côté serveur. #}
```

Le rendu complet du widget (HTML + JS inline d'ouverture de modale) est
fait **côté Twig** par `templates/Form/icon_upload.html.twig`. Il n'y a
plus de wrapping JS global sur la classe `.icon-input`. Le bundle garde
cependant un listener JS léger (`bnine:upload:done` / `bnine:crop:done`)
pour écrire la valeur dans le champ caché après upload/crop.

Pour personnaliser les classes CSS de l'image preview :

```php
// Ajoute 'rounded-circle shadow-sm' aux classes par défaut
->add('avatar', IconUploadType::class, [
    // ...
    'img_class' => 'rounded-circle shadow-sm',
])

// Remplace totalement les classes par défaut
->add('logo', IconUploadType::class, [
    // ...
    'img_class' => 'company-logo',
    'img_class_replace' => true,
])
```

### Persistance

Le champ est un `HiddenType`. La valeur stockée est le **chemin relatif** retourné par le bundle :

- Domaines publics (`avatar`, `logo`, `icon`) : `avatar/{id}/_thumbs/{file}.{ext}` (renommage auto + thumb).
- Autres domaines : `{file}.{ext}` ou `{subdir}/{file}.{ext}`.

Pour afficher le fichier : utiliser la fonction Twig `bninefile()` (voir [twig.md](twig.md)).

### BREAKING CHANGES v1.5.6+ : rendu Twig + suppression de `.icon-input`

Avant la v1.5.6, le widget `IconUploadType` injectait tout le wrapping
HTML (preview + bouton "Modifier") via JS sur la classe `.icon-input`.
C'est désormais fait **côté Twig** dans `templates/Form/icon_upload.html.twig`
et le theme `_theme.html.twig` (branche `'icon_upload' in block_prefixes`).

**Impacts éventuels pour les apps hotes :**

- Si vous aviez customisé le CSS via la classe `.icon-input` sur le champ
  caché → elle n'est plus posée. Le nouveau DOM utilise
  `.icon-upload-wrapper` (div parente) + `.icon-upload-preview` (img) +
  `.icon-upload-btn` (bouton) + `.icon-input-hidden` (input caché).
  Les classes par défaut de l'img sont `mb-2 icon-upload-preview`. Utilisez
  l'option `img_class` pour ajouter les vôtres.

- Si vous aviez du JS applicatif qui ciblait `.icon-input` (jQuery /
  addEventListener) → basculez sur `.icon-upload-wrapper` ou sur
  l'`id` du champ caché (généré par Symfony).

- L'event CustomEvent `bnine:iconupload:change` continue d'être dispatché
  (voir [custom-events.md](custom-events.md)). Le `bnine:crop:done` et
  `bnine:upload:done` aussi.

**Migration recommandée :**

```twig
{# Avant : ciblage via .icon-input #}
{# Votre sélecteur jQuery $('.icon-input').on('change', ...) #}

{# Après : ciblage via .icon-upload-wrapper (exemple Bootstrap) #}
{# Votre sélecteur jQuery $('.icon-upload-wrapper input[type="hidden"]').on('change', ...) #}
```

Ou mieux : écoutez les CustomEvents (voir [custom-events.md](custom-events.md)) — ça découple votre code
du DOM du bundle.

---

## `SelectFileType` — Form Type

Champ caché qui ouvre une **galerie modale** et permet de sélectionner une ou plusieurs images existantes. Idéal pour « choisir une image existante » sans ré-uploader.

### Options

| Option | Type | Défaut | Description |
|--------|------|--------|-------------|
| `mode` | string | `"single"` | `"single"` = 1 image (stocke un string), `"multiple"` = N images (stocke un JSON array) |
| `access` | string | `"write"` | `"write"` = modale avec upload/dossier/suppression, `"read"` = modale en navigation seule |
| `layout` | string | `"inline"` | `"inline"` = preview\|boutons, `"column"` = preview/boutons |
| `domain` | string | `"sample"` | Domaine de stockage |
| `entity_id` | int\|string | `1` | ID de l'entité propriétaire |
| `required` | bool | `false` | Ajoute l'attribut HTML `required` sur l'input caché |
| `show_name` | bool | `false` | Affiche le nom du fichier sous chaque vignette |
| `max_height` | int\|null | `100` | Hauteur max de la preview (px) |
| `grid_cols` | int | `4` | Nb colonnes de la grille (mode `multiple` uniquement) |
| `placeholder` | string\|null | auto | Texte affiché quand aucune image n'est sélectionnée |

> Les valeurs `mode`, `access`, `layout` sont validées par `setAllowedValues`. Les types sont validés par `setAllowedTypes` — un mauvais type lève une `InvalidOptionsException` au `buildForm`.

### Exemple Form Type

```php
use Bnine\FilesBundle\Form\Type\SelectFileType;

$builder
    // Image de couverture : SelectFileType single
    ->add('image', SelectFileType::class, [
        'label'       => 'Image de couverture',
        'mode'        => 'single',
        'access'      => 'write',
        'layout'      => 'inline',
        'domain'      => 'sample',
        'entity_id'   => 1,
        'required'    => true,
        'show_name'   => true,
        'max_height'  => 150,
    ])
    // Galerie : SelectFileType multiple
    ->add('gallery', SelectFileType::class, [
        'label'       => 'Galerie d\'images',
        'mode'        => 'multiple',
        'access'      => 'write',
        'layout'      => 'inline',
        'domain'      => 'sample',
        'entity_id'   => 1,
        'required'    => false,
        'show_name'   => true,
        'max_height'  => 100,
        'grid_cols'   => 5,
    ]);
```

### DTO

```php
use Symfony\Component\Validator\Constraints as Assert;

class ArticleDto
{
    #[Assert\NotBlank(message: 'L\'image est obligatoire.')]
    public ?string $image = null;     // single : chemin unique

    public string $gallery = '[]';    // multiple : JSON array de chemins
}
```

### Récupération côté serveur

```php
$form->handleRequest($request);
if ($form->isSubmitted() && $form->isValid()) {
    $data = $form->getData();

    // $data->image   : string|null   ex: 'sample/1/01-008.jpg'
    // $data->gallery : string JSON   ex: '["sample/1/a.jpg","sample/1/b.jpg"]'

    $paths = json_decode($data->gallery ?? '[]', true) ?: [];
}
```

### Rendu Twig

```twig
{{ form_row(form.image) }}     {# tout-en-un : label + widget + erreurs #}

{# Ou détaillé : #}
{{ form_label(form.image) }}
{{ form_widget(form.image) }}
```

> **Pré-requis** : le theme Twig `@BnineFilesBundle/Form/_theme.html.twig` doit être activé dans `twig.yaml` (cf. section 4 de [installation.md](installation.md)). Sans ce theme, le widget n'est pas rendu correctement (juste un input caché).

### Affichage final

```twig
{# single #}
<img src="{{ bninefile(article.image) }}" alt="">

{# multiple #}
{% for path in paths %}
    <img src="{{ bninefile(path) }}" alt="">
{% endfor %}
```