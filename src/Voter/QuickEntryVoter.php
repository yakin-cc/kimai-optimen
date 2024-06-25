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
use App\Timesheet\TrackingModeService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class QuickEntryVoter extends Voter
{
    private $permissionManager;
    private $trackingModeService;

    public function __construct(RolePermissionManager $permissionManager, TrackingModeService $trackingModeService)
    {
        $this->permissionManager = $permissionManager;
        $this->trackingModeService = $trackingModeService;
    }

    /**
     * @param string $attribute
     * @param mixed $subject
     * @return bool
     */
    protected function supports($attribute, $subject)
    {
        return 'quick-entry' === $attribute;
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

        if (!$this->permissionManager->hasRolePermission($user, 'weekly_own_timesheet')) {
            return false;
        }

        if (!$this->permissionManager->hasRolePermission($user, 'edit_own_timesheet')) {
            return false;
        }

        $mode = $this->trackingModeService->getActiveMode();

        if ($mode->canEditDuration() || $mode->canEditEnd()) {
            return true;
        }

        return false;
    }
}
