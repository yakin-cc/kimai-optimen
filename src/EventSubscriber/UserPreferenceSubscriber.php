<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\EventSubscriber;

use App\Entity\User;
use App\Form\Type\SkinType;
use App\Form\Type\ReportType;
use App\Entity\UserPreference;
use App\Event\PrepareUserEvent;
use App\Form\Type\LanguageType;
use App\Form\Type\TimezoneType;
use App\Event\UserPreferenceEvent;
use App\Form\Type\InitialViewType;
use App\Form\Type\ThemeLayoutType;
use App\Form\Type\CalendarViewType;
use App\Form\Type\FirstWeekDayType;
use App\Reporting\ReportingService;
use App\Configuration\SystemConfiguration;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\DateIntervalType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;


final class UserPreferenceSubscriber implements EventSubscriberInterface
{
    private $eventDispatcher;
    private $voter;
    private $configuration;

    public function __construct(EventDispatcherInterface $eventDispatcher, AuthorizationCheckerInterface $voter, SystemConfiguration $systemConfiguration)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->voter = $voter;
        $this->configuration = $systemConfiguration;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PrepareUserEvent::class => ['loadUserPreferences', 200]
        ];
    }

    /**
     * @param User $user
     * @return UserPreference[]
     */
    public function getDefaultPreferences(User $user)
    {
        $timezone = $this->configuration->getUserDefaultTimezone();
        if (null === $timezone) {
            $timezone = date_default_timezone_get();
        }

        $enableDefaultReport = $this->voter->isGranted('view_reporting');
        $enableHourlyRate = false;
        $hourlyRateOptions = [];

        if ($this->voter->isGranted('hourly-rate', $user)) {
            $enableHourlyRate = true;
            $hourlyRateOptions = ['currency' => $this->configuration->getUserDefaultCurrency()];
        }

        return [
            (new UserPreference())
                ->setName(UserPreference::HOURLY_RATE)
                ->setValue(0)
                ->setOrder(100)
                ->setSection('rate')
                ->setType(MoneyType::class)
                ->setEnabled($enableHourlyRate)
                ->setOptions($hourlyRateOptions)
                ->addConstraint(new Range(['min' => 0])),

            (new UserPreference())
                ->setName(UserPreference::INTERNAL_RATE)
                ->setValue(null)
                ->setOrder(101)
                ->setSection('rate')
                ->setType(MoneyType::class)
                ->setEnabled($enableHourlyRate)
                ->setOptions(array_merge($hourlyRateOptions, ['label' => 'label.rate_internal', 'required' => false]))
                ->addConstraint(new Range(['min' => 0])),

            (new UserPreference())
                ->setName(UserPreference::TIMEZONE)
                ->setValue($timezone)
                ->setOrder(200)
                ->setSection('locale')
                ->setType(TimezoneType::class),

            (new UserPreference())
                ->setName(UserPreference::LOCALE)
                ->setValue($this->configuration->getUserDefaultLanguage())
                ->setOrder(250)
                ->setSection('locale')
                ->setType(LanguageType::class),

            (new UserPreference())
                ->setName(UserPreference::FIRST_WEEKDAY)
                ->setValue(User::DEFAULT_FIRST_WEEKDAY)
                ->setOrder(300)
                ->setSection('locale')
                ->setType(FirstWeekDayType::class),

            (new UserPreference())
                ->setName(UserPreference::HOUR_24)
                ->setValue(true)
                ->setOrder(305)
                ->setSection('locale')
                ->setType(CheckboxType::class),

            (new UserPreference())
                ->setName(UserPreference::SKIN)
                ->setValue($this->configuration->getUserDefaultTheme())
                ->setOrder(400)
                ->setSection('theme')
                ->setType(SkinType::class),

            (new UserPreference())
                ->setName('theme.layout')
                ->setValue('fixed')
                ->setOrder(450)
                ->setSection('theme')
                ->setType(ThemeLayoutType::class),

            (new UserPreference())
                ->setName('theme.collapsed_sidebar')
                ->setValue(false)
                ->setOrder(500)
                ->setSection('theme')
                ->setType(CheckboxType::class),

            (new UserPreference())
                ->setName('theme.update_browser_title')
                ->setValue(true)
                ->setOrder(550)
                ->setSection('theme')
                ->setType(CheckboxType::class),

            (new UserPreference())
                ->setName('calendar.initial_view')
                ->setValue(CalendarViewType::DEFAULT_VIEW)
                ->setOrder(600)
                ->setSection('behaviour')
                ->setType(CalendarViewType::class),

            (new UserPreference())
                ->setName('reporting.initial_view')
                ->setValue(ReportingService::DEFAULT_VIEW)
                ->setOrder(650)
                ->setSection('behaviour')
                ->setEnabled($enableDefaultReport)
                ->setType(ReportType::class),

            (new UserPreference())
                ->setName('login.initial_view')
                ->setValue(InitialViewType::DEFAULT_VIEW)
                ->setOrder(700)
                ->setSection('behaviour')
                ->setType(InitialViewType::class),

            (new UserPreference())
                ->setName('timesheet.daily_stats')
                ->setValue(false)
                ->setOrder(800)
                ->setSection('behaviour')
                ->setType(CheckboxType::class),

            (new UserPreference())
                ->setName('timesheet.export_decimal')
                ->setValue(false)
                ->setOrder(900)
                ->setSection('behaviour')
                ->setType(CheckboxType::class),

            (new UserPreference())
                ->setName(UserPreference::SESSION_TIMEOUT)
                ->setValue(new \DateInterval("PT1H"))
                ->setOrder(1000)
                ->setType(DateIntervalType::class)
                ->setEnabled(true)
                ->addConstraint(new Assert\Range([
                    'min' => 60,          // Minimum value of 60 seconds (1 minute)
                    'max' => 86400,       // Maximum value of 86400 seconds (24 hours)
                    'minMessage' => 'The session timeout must be at least {{ limit }} seconds.',
                    'maxMessage' => 'The session timeout cannot exceed {{ limit }} seconds.',
                ]))
                ->setOptions([
                    'with_years' => false,
                    'with_months' => false,
                    'with_days' => false,
                    'with_hours' => true,
                    'with_minutes' => true,
                    'with_seconds' => true,
                    'widget' => 'integer', // Use integers for simplicity
                    'label' => 'Session Timeout',
                ]),
        ];
    }

    /**
     * @param PrepareUserEvent $event
     */
    public function loadUserPreferences(PrepareUserEvent $event)
    {
        $user = $event->getUser();

        $event = new UserPreferenceEvent($user, $this->getDefaultPreferences($user));
        $this->eventDispatcher->dispatch($event);

        foreach ($event->getPreferences() as $preference) {
            $userPref = $user->getPreference($preference->getName());
            if (null !== $userPref) {
                $userPref
                    ->setType($preference->getType())
                    ->setConstraints($preference->getConstraints())
                    ->setEnabled($preference->isEnabled())
                    ->setOptions($preference->getOptions())
                    ->setOrder($preference->getOrder())
                    ->setSection($preference->getSection())
                ;

                // Convert the stored string to DateInterval
                if ($userPref->getName() === UserPreference::SESSION_TIMEOUT) {
                    $value = $userPref->getValue();
                    if (is_string($value)) {
                        try {
                            $userPref->setValue(new \DateInterval($value));
                        } catch (\Exception $e) {
                            // Handle invalid DateInterval format if needed
                        }
                    }
                }

            } else {
                $user->addPreference($preference);
            }
        }
    }
}
