<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Voter;

use App\Entity\User;
use App\Security\RolePermissionManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * A voter to check permissions on user profiles.
 */
final class UserVoter extends Voter
{
    private const ALLOWED_ATTRIBUTES = [
        'view',
        'edit',
        'roles',
        'teams',
        'password',
        'delete',
        'preferences',
        'api-token',
        'hourly-rate',
        'view_team_member',
    ];

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
        if (!($subject instanceof User)) {
            return false;
        }

        if (!\in_array($attribute, self::ALLOWED_ATTRIBUTES)) {
            return false;
        }

        return true;
    }

    /**
     * @param string $attribute
     * @param User $subject
     * @param TokenInterface $token
     * @return bool
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();

        if (!($user instanceof User)) {
            return false;
        }

        if ($attribute === 'view_team_member') {
            if ($subject->getId() !== $user->getId()) {
                return false;
            }

            return $this->permissionManager->hasRolePermission($user, 'view_team_member');
        }

        if ($attribute === 'delete') {
            if ($subject->getId() === $user->getId()) {
                return false;
            }

            return $this->permissionManager->hasRolePermission($user, 'delete_user');
        }

        if ($attribute === 'password') {
            if (!$subject->isInternalUser()) {
                return false;
            }
        }

        $permission = $attribute;

        // extend me for "team" support later on
        if ($subject->getId() === $user->getId()) {
            $permission .= '_own';
        } else {
            $permission .= '_other';
        }

        $permission .= '_profile';

        return $this->permissionManager->hasRolePermission($user, $permission);
    }
}
