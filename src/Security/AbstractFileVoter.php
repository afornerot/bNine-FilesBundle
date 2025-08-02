<?php

namespace Bnine\FilesBundle\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

abstract class AbstractFileVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const DELETE = 'delete';

    protected function supports(string $attribute, $subject): bool
    {
        // Le subject doit être un tableau [domain, id]
        return in_array($attribute, [self::VIEW, self::EDIT])
            && is_array($subject)
            && 2 === count($subject);
    }

    abstract protected function canView(string $domain, $id, TokenInterface $token): bool;

    abstract protected function canEdit(string $domain, $id, TokenInterface $token): bool;

    abstract protected function canDelete(string $domain, $id, TokenInterface $token): bool;

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        [$domain, $id] = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($domain, $id, $token),
            self::EDIT => $this->canEdit($domain, $id, $token),
            self::DELETE => $this->canDelete($domain, $id, $token),
            default => false,
        };
    }
}
