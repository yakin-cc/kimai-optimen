<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Twig\Runtime;

use App\Widget\WidgetException;
use App\Widget\WidgetInterface;
use App\Widget\WidgetService;
use Twig\Extension\RuntimeExtensionInterface;

final class WidgetExtension implements RuntimeExtensionInterface
{
    /**
     * @var WidgetService
     */
    private $service;

    public function __construct(WidgetService $service)
    {
        $this->service = $service;
    }

    /**
     * @param WidgetInterface|string $widget
     * @param array $options
     * @return string
     * @throws WidgetException
     */
    public function renderWidget($widget, array $options = [])
    {
        if (!($widget instanceof WidgetInterface) && !\is_string($widget)) {
            throw new \InvalidArgumentException('Widget must either implement WidgetInterface or be a string');
        }

        if (\is_string($widget)) {
            if (!$this->service->hasWidget($widget)) {
                throw new \InvalidArgumentException(sprintf('Unknown widget "%s" requested', $widget));
            }

            $widget = $this->service->getWidget($widget);
        }

        $renderer = $this->service->findRenderer($widget);

        return $renderer->render($widget, $options);
    }
}
