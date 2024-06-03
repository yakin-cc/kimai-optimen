<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Select the pattern that will be used for calendar entry titles.
 */
class CalendarTitlePatternType extends AbstractType
{
    public const PATTERN_CUSTOMER = '{customer}';
    public const PATTERN_PROJECT = '{project}';
    public const PATTERN_ACTIVITY = '{activity}';
    public const PATTERN_DESCRIPTION = '{description}';
    public const SPACER = ' - ';
    public const PATTERN_ACTIVITY_DESCRIPTION = self::PATTERN_ACTIVITY . self::SPACER . self::PATTERN_DESCRIPTION;
    public const PATTERN_PROJECT_DESCRIPTION = self::PATTERN_PROJECT . self::SPACER . self::PATTERN_DESCRIPTION;
    public const PATTERN_CUSTOMER_DESCRIPTION = self::PATTERN_CUSTOMER . self::SPACER . self::PATTERN_DESCRIPTION;
    public const PATTERN_PROJECT_CUSTOMER = self::PATTERN_PROJECT . self::SPACER . self::PATTERN_CUSTOMER;

    private $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $customer = $this->translator->trans('label.customer');
        $project = $this->translator->trans('label.project');
        $activity = $this->translator->trans('label.activity');
        $description = $this->translator->trans('label.description');

        $resolver->setDefaults([
            'label' => 'label.choice_pattern',
            'choices' => [
                $activity => CalendarTitlePatternType::PATTERN_ACTIVITY,
                $project => CalendarTitlePatternType::PATTERN_PROJECT,
                $customer => CalendarTitlePatternType::PATTERN_CUSTOMER,
                $description => CalendarTitlePatternType::PATTERN_DESCRIPTION,
                $activity . self::SPACER . $description => CalendarTitlePatternType::PATTERN_ACTIVITY_DESCRIPTION,
                $project . self::SPACER . $description => CalendarTitlePatternType::PATTERN_PROJECT_DESCRIPTION,
                $customer . self::SPACER . $description => CalendarTitlePatternType::PATTERN_CUSTOMER_DESCRIPTION,
                $project . self::SPACER . $customer => CalendarTitlePatternType::PATTERN_PROJECT_CUSTOMER,
            ]
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }
}
