<?php

namespace App\Security;

use Bnine\FilesBundle\Security\AbstractFileVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Voter concret pour le sample : autorise TOUT (mode démo sans auth).
 * En production, restreignez par user/role/domain.
 */
class FileVoter extends AbstractFileVoter
{
    private const PUBLIC_DOMAINS = ['avatar', 'logo', 'icon', 'blog', 'gallery', 'sample'];

    protected function canView(string $domain, $id, TokenInterface $token): bool
    {
        return true;
    }

    protected function canEdit(string $domain, $id, TokenInterface $token): bool
    {
        return true;
    }

    protected function canDelete(string $domain, $id, TokenInterface $token): bool
    {
        return true;
    }
}
