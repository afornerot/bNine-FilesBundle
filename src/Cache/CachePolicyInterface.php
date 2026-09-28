<?php

namespace Bnine\FilesBundle\Cache;

/**
 * Stratégie de cache HTTP pour les fichiers servis par le bundle
 * (routes bninefiles_files_image et bninefiles_files_thumbnail).
 *
 * Le bundle applique par défaut un cache public de 30 jours.
 * L'application hôte peut implémenter cette interface pour surcharger
 * la politique de cache fichier par fichier.
 *
 * Le FileVoter (sécurité) reste distinct : il décide QUI peut accéder.
 * Le CachePolicy décide COMBIEN DE TEMPS le fichier peut être cache
 * par les clients et les proxies (CDN, navigateurs, reverse proxies).
 */
interface CachePolicyInterface
{
    /**
     * Retourne la duree de cache en secondes pour ce fichier, ou null
     * pour utiliser la valeur par defaut du bundle (30 jours).
     *
     * Valeurs speciales :
     *   - 0 : pas de cache (le navigateur doit revalider à chaque requete)
     *   - null : defaut du bundle (86400 * 30 = 30 jours)
     *
     * Le bundle appelle setMaxAge() sur la reponse si la valeur est > 0.
     * Si la valeur est 0, aucun header Cache-Control n'est ajoute
     * (le navigateur applique son cache par défaut).
     */
    public function getCacheMaxAge(string $domain, string $id, string $filePath): ?int;

    /**
     * Indique si la reponse doit etre cacheable par les caches partages
     * (CDN, reverse proxies) ou uniquement par le navigateur de l'utilisateur.
     *
     * Retourne :
     *   - true : setPublic() (cacheable par tous les caches)
     *   - false : setPrivate() (cacheable uniquement par le navigateur)
     *   - null : defaut du bundle (setPublic())
     *
     * Exemple d'usage : un avatar doit etre 'private' (sensible, pas de cache CDN),
     * un logo ou une image de blog doit etre 'public' (cacheable par CDN).
     */
    public function isPublicCacheable(string $domain, string $id, string $filePath): ?bool;
}