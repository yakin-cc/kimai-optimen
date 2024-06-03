<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Event;

use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * This event should be used, if a user profile is loaded and want to fill the dynamic user preferences
 */
final class PrepareUserEvent extends Event
{
    /**
     * @deprecated since 1.4, will be removed with 2.0
     */
    public const PREPARE = PrepareUserEvent::class;
    /**
     * @var User
     */
    private $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
