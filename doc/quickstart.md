# Démarrage rapide : un navigateur de fichiers

Afficher un navigateur de fichiers complet (upload, dossiers, suppression, téléchargement) pour une entité, en deux lignes de Twig :

```php
// src/Controller/ArticleController.php
#[Route('/admin/articles/{id}/files', name: 'admin_article_files')]
public function files(Article $article): Response
{
    return $this->render('admin/article_files.html.twig', [
        'domain' => 'article',
        'entity_id' => $article->getId(),
    ]);
}
```

```twig
{# templates/admin/article_files.html.twig #}
{% extends 'admin/base.html.twig' %}

{% block body %}
    <h1>Fichiers attachés à l'article #{{ entity_id }}</h1>

    {# Mode écriture (upload + dossiers + suppression) #}
    {{ render(controller(
        'Bnine\\FilesBundle\\Controller\\FileController::browse',
        { domain: domain, id: entity_id, editable: 1 }
    )) }}
{% endblock %}
```

C'est tout : le navigateur est fonctionnel. Pour passer en lecture seule, mettre `editable: 0`.

Pour une **galerie d'images** avec lightbox :

```twig
{{ render(controller(
    'Bnine\\FilesBundle\\Controller\\FileController::gallery',
    { domain: 'article', id: entity_id, editable: 1 }
)) }}
```