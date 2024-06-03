<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Voter;

use App\Entity\Team;
use App\Entity\User;
use App\Security\RolePermissionManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class TeamVoter extends Voter
{
    /**
     * support rules based on the given $subject (here: Team)
     */
    private const ALLOWED_ATTRIBUTES = [
        'view',
        'edit',
        'delete',
    ];

    private $permissionManager;

    public function __construct(RolePermissionManager $permissionManager)
    {
        $this->permissionManager = $permissionManager;
    }

    /**
     * @param string $attribute
     * @param Team $subject
     * @return bool
     */
    protected function supports($attribute, $subject)
    {
        if (!($subject instanceof Team)) {
            return false;
        }

        if (!\in_array($attribute, self::ALLOWED_ATTRIBUTES)) {
            return false;
        }

        return true;
    }

    /**
     * @param string $attribute
     * @param Team $subject
     * @param TokenInterface $token
     * @return bool
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        switch ($attribute) {
            case 'edit':
            case 'delete':
                // changing existing teams should be limited to admins and teamleads
                if (!$user->isAdmin() && !$user->isSuperAdmin() && !$user->isTeamleadOf($subject)) {
                    return false;
                }
        }

        return $this->permissionManager->hasRolePermission($user, $attribute . '_team');
    }
}
