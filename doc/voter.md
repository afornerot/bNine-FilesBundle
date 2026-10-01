# Voter de sécurité

`Bnine\FilesBundle\Security\AbstractFileVoter` gère 3 attributs (`view`, `edit`, `delete`) avec un subject de type `[domain, id]`.

## Voter concret

```php
namespace App\Security;

use Bnine\FilesBundle\Security\AbstractFileVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class FileVoter extends AbstractFileVoter
{
    /** Domaines publics : pas d'auth requise */
    private const PUBLIC_DOMAINS = ['avatar', 'logo', 'icon'];

    protected function canView(string $domain, $id, TokenInterface $token): bool
    {
        if (in_array($domain, self::PUBLIC_DOMAINS, true)) {
            return true;
        }
        return $this->canManage($domain, $id, $token);
    }

    protected function canEdit(string $domain, $id, TokenInterface $token): bool
    {
        return $this->canManage($domain, $id, $token);
    }

    protected function canDelete(string $domain, $id, TokenInterface $token): bool
    {
        return $this->canManage($domain, $id, $token);
    }

    private function canManage(string $domain, $id, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user) {
            return false;
        }

        // Exemple : un user gère ses propres fichiers
        if ('user-document' === $domain && (int) $id === $user->getId()) {
            return true;
        }

        // Admins gèrent tout
        return in_array('ROLE_ADMIN', $user->getRoles(), true);
    }
}
```

## Déclaration du service

```yaml
# config/services.yaml
App\Security\FileVoter:
    tags: ['security.voter']
```

> **Note** : le voter **doit** être tagué `security.voter` pour être appelé par `denyAccessUnlessGranted`. Sans voter actif, Symfony lève une `AccessDeniedException`.