<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Widget\Type;

interface AuthorizedWidget
{
    /**
     * Return a list of granted syntax string.
     * If ANY of the given permission strings matches, access is granted.
     *
     * @return string[]
     */
    public function getPermissions(): array;
}
