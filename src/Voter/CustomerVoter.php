<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Voter;

use App\Entity\Customer;
use App\Entity\Team;
use App\Entity\User;
use App\Security\RolePermissionManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * A voter to check authorization on Customers.
 */
final class CustomerVoter extends Voter
{
    /**
     * supported attributes/rules based on the given customer
     */
    private const ALLOWED_ATTRIBUTES = [
        'view',
        'create',
        'edit',
        'budget',
        'time',
        'delete',
        'permissions',
        'comments',
        'comments_create',
        'details',
        'access',
    ];

    private $permissionManager;

    public function __construct(RolePermissionManager $permissionManager)
    {
        $this->permissionManager = $permissionManager;
    }

    /**
     * @param string $attribute
     * @param Customer $subject
     * @return bool
     */
    protected function supports($attribute, $subject)
    {
        if (!($subject instanceof Customer)) {
            return false;
        }

        if (!\in_array($attribute, self::ALLOWED_ATTRIBUTES)) {
            return false;
        }

        return true;
    }

    /**
     * @param string $attribute
     * @param Customer $subject
     * @param TokenInterface $token
     * @return bool
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // this is a virtual permission, only meant to be used by developer
        // it checks if access to the given customer is potentially possible
        if ($attribute === 'access') {
            if ($subject->getTeams()->count() === 0) {
                return true;
            }

            foreach ($subject->getTeams() as $team) {
                if ($user->isInTeam($team)) {
                    return true;
                }
            }

            if ($user->canSeeAllData()) {
                return true;
            }

            return false;
        }

        if ($this->permissionManager->hasRolePermission($user, $attribute . '_customer')) {
            return true;
        }

        // those cannot be assigned to teams
        if (\in_array($attribute, ['create', 'delete'])) {
            return false;
        }

        $hasTeamleadPermission = $this->permissionManager->hasRolePermission($user, $attribute . '_teamlead_customer');
        $hasTeamPermission = $this->permissionManager->hasRolePermission($user, $attribute . '_team_customer');

        if (!$hasTeamleadPermission && !$hasTeamPermission) {
            return false;
        }

        /** @var Team $team */
        foreach ($subject->getTeams() as $team) {
            if ($hasTeamleadPermission && $user->isTeamleadOf($team)) {
                return true;
            }

            if ($hasTeamPermission && $user->isInTeam($team)) {
                return true;
            }
        }

        return false;
    }
}
