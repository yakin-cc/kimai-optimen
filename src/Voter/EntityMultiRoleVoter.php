<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Voter;

use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\User;
use App\Security\RolePermissionManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class EntityMultiRoleVoter extends Voter
{
    /**
     * support rules based on the given activity/project/customer
     */
    private const ALLOWED_ATTRIBUTES = [
        'budget_money',
        'budget_time',
        'budget_any',
        'details',
    ];
    private const ALLOWED_SUBJECTS = [
        'customer',
        'project',
        'activity',
    ];

    private $permissionManager;

    public function __construct(RolePermissionManager $permissionManager)
    {
        $this->permissionManager = $permissionManager;
    }

    /**
     * @param string $attribute
     * @param Activity|Project|Customer|string $subject
     * @return bool
     */
    protected function supports($attribute, $subject)
    {
        if (!\in_array($attribute, self::ALLOWED_ATTRIBUTES)) {
            return false;
        }

        if (\is_string($subject) && \in_array($subject, self::ALLOWED_SUBJECTS)) {
            return true;
        }

        if ($subject instanceof Activity || $subject instanceof Project || $subject instanceof Customer) {
            return true;
        }

        return false;
    }

    /**
     * @param string $attribute
     * @param Activity|Project|Customer|string $subject
     * @param TokenInterface $token
     * @return bool
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        $suffix = null;

        if (\is_string($subject) && \in_array($subject, self::ALLOWED_SUBJECTS)) {
            $suffix = $subject;
        } elseif ($subject instanceof Activity) {
            $suffix = 'activity';
        } elseif ($subject instanceof Project) {
            $suffix = 'project';
        } elseif ($subject instanceof Customer) {
            $suffix = 'customer';
        }

        if ($suffix === null) {
            return false;
        }

        $permissions = [];

        if ($attribute === 'details') {
            $permissions[] = 'details';
        }

        if ($attribute === 'budget_money' || $attribute === 'budget_any') {
            $permissions[] = 'budget';
            $permissions[] = 'budget_teamlead';
            $permissions[] = 'budget_team';
        }

        if ($attribute === 'budget_time' || $attribute === 'budget_any') {
            $permissions[] = 'time';
            $permissions[] = 'time_teamlead';
            $permissions[] = 'time_team';
        }
        foreach ($permissions as $permission) {
            if ($this->permissionManager->hasRolePermission($user, $permission . '_' . $suffix)) {
                return true;
            }
        }

        return false;
    }
}
