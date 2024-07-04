# Optimen V1 Modifications to Kimai
**Written by: Luis Yakin Carrillo Camacho**


### Introduction
This document explains the initial set of modifications made to the default installation of the open-source application "Kimai," used internally at Optimen. These changes aim to enhance the experience for both general and administrative users by adding new filtering functionalities to the reporting of active projects and allowing users to set their session log-out timer preferences.

### Changes
#### Session Timeout Preference
Previously, the automatic log-out time for users was set by the Symfony framework for all users on the platform. This modification allows users to customize their session log-out timer. Under the __Profile > Preferences__ route, a new field, __"Session Timeout,"__ has been added to the preferences form. This field enables users to choose the exact time interval before the application automatically logs them out.

To implement this feature, several code modifications were made. First, two new options, `gc_maxlifetime` and `cookie_lifetime`, were added to the `\config\packages\framework.yaml` file, both set to 86400 seconds (24 hours). This sets the maximum time the browser can store cookies (where session data is kept) before deleting them, establishing the upper limit.

```yaml
session:
    cookie_lifetime: 86400    # 1 day
    gc_maxlifetime: 86400     # 1 day
```

The Preferences form, defined as a collection of different data types in `\src\forms\UserPreferencesForm.php` and built in `\src\forms\type\UserPreferenceType.php`, was not modified. However, `\src\EventSubscriber\UserPreferenceSubscriber.php`, which sets the default fields and behavior for the preferences form, was updated. A new default user preference, `session_timeout`, was created, defining the corresponding default values, options, and constraints. The `\src\Entity\UserPreference` entity was also modified to include the `SESSION_TIMEOUT` constant.
```php 
//UserPreferenceSubscriber.php

final class UserPreferenceSubscriber implements EventSubscriberInterface
{
    // . . . 
    public function getDefaultPreferences(User $user)
    {
        // . . .
        return[
            // . . .
            (new UserPreference())
                ->setName(UserPreference::SESSION_TIMEOUT)
                ->setValue(new \DateInterval("PT1H"))   //Default Value set to 1 Hour
                ->setOrder(1000)
                ->setType(DateIntervalType::class)
                ->setEnabled(true)
                ->addConstraint(new AppAssert\DateIntervalSessionRange([
                    'minSeconds' => 60,          // Minimum value of 60 seconds (1 minute)
                    'maxSeconds' => 86400,       // Maximum value of 86400 seconds (24 hours)
                ]))
                ->setOptions([
                    'with_years' => false,
                    'with_months' => false,
                    'with_days' => false,
                    'with_hours' => true,
                    'with_minutes' => true,
                    'with_seconds' => true,
                    'widget' => 'integer', 
                    'label' => 'Session Timeout',
                ]),
        ];
    }
}
```

```php
//UserPreference.php

class UserPreference{
    // . . .
    public const SESSION_TIMEOUT = 'session_timeout';
}
```
The validation of the time constraint is defined in `\src\Validator\Constraints\DateIntervalSessionRange`, and it's logic is handled in `\src\Validator\Constraints\DateIntervalSessionRangeValidator`, where the given `DateInterval` object is separated in hours, minutes and seconds, converted to seconds and comparated to de minimum and maximum allowed values. 
```php
//DateIntervalSessionRangeValidator.php

namespace App\Validator\Constraints;

use DateInterval;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class DateIntervalSessionRangeValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof DateIntervalSessionRange) {
            throw new UnexpectedTypeException($constraint, DateIntervalSessionRange::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!$value instanceof DateInterval) {
            throw new UnexpectedTypeException($value, DateInterval::class);
        }

        $totalSeconds = $value->s + ($value->i * 60) + ($value->h * 3600);
        
        if ($totalSeconds < $constraint->minSeconds) {
            $this->context->buildViolation($constraint->minMessage)
                ->setParameter('{{ limit }}', $constraint->minSeconds)
                ->addViolation();
            return;
        }

        if ($totalSeconds > $constraint->maxSeconds) {
            $this->context->buildViolation($constraint->maxMessage)
                ->setParameter('{{ limit }}', $constraint->maxSeconds)
                ->addViolation();
            return;
        }
    }
}
```


To store the inputted data in the UserPreference entity, the `\src\Controller\ProfileController.php` was modified in the `preferencesAction` method. Here, once the form is submitted and validated, the session timeout preference is formatted using a DateInterval format, set to the entity, and then persisted.

```php
final class ProfileController extends AbstractController
{
    // . . . 
    public function preferencesAction(User $profile, Request $request, EventDispatcherInterface $dispatcher): Response
    {
        // . . .
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                // . . .
                $sessionTimeoutPref = $profile->getPreference(UserPreference::SESSION_TIMEOUT);
                if ($sessionTimeoutPref) {
                    $interval = $sessionTimeoutPref->getValue();
                    if ($interval instanceof \DateInterval) {
                        $sessionTimeoutPref->setValue($interval->format('P%yY%mM%dDT%hH%iM%sS'));
                    }
                }
            }
        }
    }
}
```

These changes display the form field, define it in the entity, and store it in the database. The logic for the timeout timer was implemented in `\src\Configuration\SessionTimeoutListener.php`. The `SessionTimeoutListener` class waits for a request event, retrieves the current user and their stored preference value, and migrates the current session to a new one, setting the cookie lifetime to the user's preference. This refreshes the session lifetime each time a request is triggered.

#### Project Details Reporting Filters

The idea of this modification was to improve and enhance the reporting view of a project, allowing the administrator to go from the general details, to more specific reporting, adding three new filters: Month, User and Activity. These filters are dynamically updated acording to both, the project selected, and the month selected, and they modify the way the charts and statistical data in the `reporting > project_details` view are displayed, so there is only shown the reporting corresponding to the set filters.

The first step to create this modification was to modify the `\src\Reporting\ProjectDetails\ProjectDetailsForm.php` adding four more fields to the form, which already had the project field: month, selectedUser, activity and a reset button. These form fields allow the user to choose their desired filter. The whole code was restructred for clarity, building each form field separtatedly and building tyhe whole form with the `buildForm` method.

An event listener was added, which is the one that allows to dynamically update the options of each filter, acording the selected project:
```php
public function onPreSubmit(FormEvent $event)
    {
        $data = $event->getData();
        $form = $event->getForm();
        $data = $this->getDefaultData($data);
        $selectedProjectId = $data['project'] ?? null;

        // Reset fields if project has changed
        if ($this->isProjectChanged($data)) {
            $data = $this->resetData($data);
            $event->setData($data);
        }

        // Update form fields if a project is selected
        if ($selectedProjectId) {
            $this->updateFormFields($form, $selectedProjectId, $data);
            $this->session->set('previousProjectId', $selectedProjectId);
        }
    }
```
This method triggers before submitting the data to the form, checks if the selected project has changed to set all filters to null by default, and if a project has ben selected, updates it's fields and saves the current selected project in the session. The `updateFormFields`method then 





