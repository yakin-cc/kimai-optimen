<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Voter;

use App\Entity\Activity;
use App\Entity\User;
use App\Security\RolePermissionManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * A voter to check the free-configurable permission from "kimai.permissions".
 */
final class RolePermissionVoter extends Voter
{
    private $permissionManager;

    public function __construct(RolePermissionManager $permissionManager)
    {
        $this->permissionManager = $permissionManager;
    }

    /**
     * @param string $attribute
     * @param mixed $subject
     * @return bool
     */
    protected function supports($attribute, $subject)
    {
        // we only work on single strings that have no subject
        if (null !== $subject) {
            return false;
        }

        return $this->permissionManager->isRegisteredPermission($attribute);
    }

    /**
     * @param string $attribute
     * @param Activity $subject
     * @param TokenInterface $token
     * @return bool
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();

        if (!($user instanceof User)) {
            return false;
        }

        return $this->permissionManager->hasRolePermission($user, $attribute);
    }
}
