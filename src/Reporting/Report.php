<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Reporting;

final class Report implements ReportInterface
{
    private $id;
    private $label;
    private $route;
    private $reportIcon = 'reporting';

    public function __construct(string $id, string $route, string $label, ?string $reportIcon = null)
    {
        $this->id = $id;
        $this->route = $route;
        $this->label = $label;
        if (null !== $reportIcon) {
            $this->reportIcon = $reportIcon;
        }
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getReportIcon(): string
    {
        return $this->reportIcon;
    }
}
