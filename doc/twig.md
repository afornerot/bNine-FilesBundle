# Extension Twig `bninefile()`

Génère l'URL publique d'un fichier stocké pour un attribut `src` ou `href`.

## Usage

```twig
<img src="{{ bninefile(user.avatar) }}" alt="Avatar">
<a href="{{ bninefile(article.document) }}">Télécharger</a>
```

## Logique de résolution

| Entrée | Sortie |
|--------|--------|
| `null` / `''` | `''` |
| URL `http(s)://...` | renvoyée telle quelle |
| Chemin `/bninefiles/...` | renvoyé tel quel |
| Chemin `bundles/...` | préfixé par `/` |
| Chemin `uploads/...` (legacy) | préfixé par `/` |
| Format `{domain}/{id}/{path}` | `/bninefiles/image/{domain}/{id}?path={path}` |

## Exemple

```twig
{{ bninefile('avatar/7/a1b2c3d4.jpg') }}
{# → /bninefiles/image/avatar/7?path=a1b2c3d4.jpg #}

{{ bninefile('avatar/0/_thumbs/abc.jpg') }}
{# → /bninefiles/image/avatar/0?path=_thumbs%2Fabc.jpg #}
```