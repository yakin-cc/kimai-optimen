<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Event;

/**
 * Triggered for project detail pages, to add additional content boxes.
 *
 * @see https://symfony.com/doc/5.4/templates.html#embedding-controllers
 */
final class ProjectDetailControllerEvent extends AbstractProjectEvent
{
    private $controller = [];

    public function addController(string $controller): void
    {
        $this->controller[] = $controller;
    }

    public function getController(): array
    {
        return $this->controller;
    }
}
