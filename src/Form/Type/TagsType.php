<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form\Type;

use App\Configuration\SystemConfiguration;
use Symfony\Component\Form\AbstractType;

final class TagsType extends AbstractType
{
    private $configuration;

    public function __construct(SystemConfiguration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        if ($this->configuration->isAllowTagCreation()) {
            return TagsInputType::class;
        }

        return TagsSelectType::class;
    }
}
