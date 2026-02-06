<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, User>
 */
class ChildAccessVoter extends Voter
{
    public const CHILD_ACCESS = 'CHILD_ACCESS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::CHILD_ACCESS && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $authenticatedUser = $token->getUser();

        if (!$authenticatedUser instanceof User) {
            return false;
        }

        # Admins can access any child's data
        if ($authenticatedUser->isAdmin()) {
            return true;
        }

        # Children can only access their own data
        return $authenticatedUser->getId() === $subject->getId();
    }
}
