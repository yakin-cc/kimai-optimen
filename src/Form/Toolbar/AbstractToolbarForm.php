<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form\Toolbar;

use App\Entity\Activity;
use App\Form\Type\ActivityType;
use App\Form\Type\CustomerType;
use App\Form\Type\DateRangeType;
use App\Form\Type\PageSizeType;
use App\Form\Type\ProjectType;
use App\Form\Type\SearchTermType;
use App\Form\Type\TagsType;
use App\Form\Type\TeamType;
use App\Form\Type\UserRoleType;
use App\Form\Type\UserType;
use App\Form\Type\VisibilityType;
use App\Repository\ActivityRepository;
use App\Repository\CustomerRepository;
use App\Repository\ProjectRepository;
use App\Repository\Query\ActivityFormTypeQuery;
use App\Repository\Query\BaseQuery;
use App\Repository\Query\CustomerFormTypeQuery;
use App\Repository\Query\ProjectFormTypeQuery;
use App\Repository\Query\TimesheetQuery;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\Choice;

/**
 * Defines the base form used for all toolbars.
 *
 * Extend this class and stack the elements defined here, they are coupled to each other and with the toolbar.js.
 */
abstract class AbstractToolbarForm extends AbstractType
{
    /**
     * Dirty hack to enable easy handling of GET form in controller and javascript.
     * Cleans up the name of all form elements (and unfortunately of the form itself).
     *
     * @return null|string
     */
    public function getBlockPrefix()
    {
        return '';
    }

    protected function addUserChoice(FormBuilderInterface $builder)
    {
        $builder->add('user', UserType::class, [
            'label' => 'label.user',
            'required' => false,
        ]);
    }

    protected function addUsersChoice(FormBuilderInterface $builder, string $field = 'users', array $options = [])
    {
        $builder->add($field, UserType::class, array_merge([
            'documentation' => [
                'type' => 'array',
                'items' => ['type' => 'integer', 'description' => 'User ID'],
                'description' => 'Array of user IDs',
            ],
            'label' => 'label.user',
            'multiple' => true,
            'required' => false,
        ], $options));
    }

    protected function addTeamChoice(FormBuilderInterface $builder)
    {
        $builder->add('team', TeamType::class, [
            'label' => 'label.team',
            'required' => false,
        ]);
    }

    protected function addTeamsChoice(FormBuilderInterface $builder, string $field = 'teams', array $options = [])
    {
        $builder->add($field, TeamType::class, array_merge([
            'documentation' => [
                'type' => 'array',
                'items' => ['type' => 'integer', 'description' => 'Team ID'],
                'description' => 'Array of team IDs',
            ],
            'label' => 'label.team',
            'multiple' => true,
            'required' => false,
        ], $options));
    }

    protected function addCustomerChoice(FormBuilderInterface $builder, array $options = [], bool $multiProject = false)
    {
        $this->addCustomerSelect($builder, $options, false, $multiProject);
    }

    protected function addCustomerMultiChoice(FormBuilderInterface $builder, array $options = [], bool $multiProject = false)
    {
        $this->addCustomerSelect($builder, $options, true, $multiProject);
    }

    private function addCustomerSelect(FormBuilderInterface $builder, array $options, bool $multiCustomer, bool $multiProject)
    {
        $name = 'customer';
        if ($multiCustomer) {
            $name = 'customers';
        }

        // just a fake field for having this field at the right position in the frontend
        $builder->add($name, CustomerType::class, [
            'documentation' => [
                'type' => 'array',
                'items' => ['type' => 'integer', 'description' => 'Customer ID'],
                'description' => 'Array of customer IDs',
            ],
            'choices' => [],
            'multiple' => $multiCustomer,
        ]);

        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) use ($builder, $options, $name, $multiCustomer, $multiProject) {
                $data = $event->getData();
                $event->getForm()->add($name, CustomerType::class, array_merge([
                    'multiple' => $multiCustomer,
                    'required' => false,
                    'project_enabled' => $multiCustomer ? 'customers' : 'customer',
                    'project_select' => $multiProject ? 'projects' : 'project',
                    'end_date_param' => '%daterange%',
                    'start_date_param' => '%daterange%',
                    'query_builder' => function (CustomerRepository $repo) use ($builder, $data, $name, $multiCustomer) {
                        $query = new CustomerFormTypeQuery();
                        $query->setUser($builder->getOption('user'));

                        if (isset($data[$name]) && !empty($data[$name])) {
                            if ($multiCustomer) {
                                $query->setCustomers($data[$name]);
                            } else {
                                $query->addCustomer($data[$name]);
                            }
                        }

                        return $repo->getQueryBuilderForFormType($query);
                    },
                ], $options));
            }
        );
    }

    protected function addVisibilityChoice(FormBuilderInterface $builder, string $label = 'label.visible')
    {
        $builder->add('visibility', VisibilityType::class, [
            'required' => false,
            'placeholder' => null,
            'label' => $label,
            'search' => false
        ]);
    }

    protected function addPageSizeChoice(FormBuilderInterface $builder)
    {
        $builder->add('pageSize', PageSizeType::class, [
            'required' => false,
            'search' => false
        ]);
    }

    protected function addUserRoleChoice(FormBuilderInterface $builder)
    {
        $builder->add('role', UserRoleType::class, [
            'required' => false,
        ]);
    }

    protected function addDateRange(FormBuilderInterface $builder, array $options, $allowEmpty = true, $required = false)
    {
        $params = [
            'required' => $required,
            'allow_empty' => $allowEmpty,
        ];

        if (\array_key_exists('timezone', $options)) {
            $params['timezone'] = $options['timezone'];
        }

        $builder->add('daterange', DateRangeType::class, $params);
    }

    protected function addDateRangeChoice(FormBuilderInterface $builder, $allowEmpty = true, $required = false)
    {
        $this->addDateRange($builder, [], $allowEmpty, $required);
    }

    protected function addProjectChoice(FormBuilderInterface $builder, array $options = [], bool $multiCustomer = false, bool $multiActivity = false)
    {
        $this->addProjectSelect($builder, $options, false, $multiCustomer, $multiActivity);
    }

    protected function addProjectMultiChoice(FormBuilderInterface $builder, array $options = [], bool $multiCustomer = false, bool $multiActivity = false)
    {
        $this->addProjectSelect($builder, $options, true, $multiCustomer, $multiActivity);
    }

    private function addProjectSelect(FormBuilderInterface $builder, array $options, bool $multiProject, bool $multiCustomer, bool $multiActivity)
    {
        $name = 'project';
        if ($multiProject) {
            $name = 'projects';
        }
        // just a fake field for having this field at the right position in the frontend
        $builder->add($name, ProjectType::class, [
            'documentation' => [
                'type' => 'array',
                'items' => ['type' => 'integer', 'description' => 'Project ID'],
                'description' => 'Array of project IDs',
            ],
            'choices' => [],
            'multiple' => $multiProject,
        ]);

        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) use ($builder, $options, $name, $multiCustomer, $multiProject, $multiActivity) {
                $data = $event->getData();
                $event->getForm()->add($name, ProjectType::class, array_merge([
                    'multiple' => $multiProject,
                    'required' => false,
                    'activity_enabled' => $multiProject ? 'projects' : 'project',
                    'activity_select' => $multiActivity ? 'activities' : 'activity',
                    'query_builder' => function (ProjectRepository $repo) use ($builder, $data, $options, $multiCustomer, $multiProject) {
                        $query = new ProjectFormTypeQuery();
                        $query->setUser($builder->getOption('user'));

                        $name = $multiCustomer ? 'customers' : 'customer';
                        if (isset($data[$name]) && !empty($data[$name])) {
                            if (\is_array($data[$name])) {
                                $query->setCustomers($data[$name]);
                            } else {
                                $query->addCustomer($data[$name]);
                            }
                        }

                        $name = $multiProject ? 'projects' : 'project';
                        if (isset($data[$name]) && !empty($data[$name])) {
                            if (\is_array($data[$name])) {
                                $query->setProjects($data[$name]);
                            } else {
                                $query->addProject($data[$name]);
                            }
                        }

                        if (isset($options['ignore_date']) && true === $options['ignore_date']) {
                            $query->setIgnoreDate(true);
                        }

                        return $repo->getQueryBuilderForFormType($query);
                    },
                ], $options));
            }
        );
    }

    protected function addActivityChoice(FormBuilderInterface $builder, array $options = [], bool $multiProject = false)
    {
        $this->addActivitySelect($builder, $options, false, $multiProject);
    }

    protected function addActivityMultiChoice(FormBuilderInterface $builder, array $options = [], bool $multiProject = false)
    {
        $this->addActivitySelect($builder, $options, true, $multiProject);
    }

    private function addActivitySelect(FormBuilderInterface $builder, array $options = [], bool $multiActivity = false, bool $multiProject = false)
    {
        $name = 'activity';
        if ($multiActivity) {
            $name = 'activities';
        }

        // just a fake field for having this field at the right position in the frontend
        $builder->add($name, ActivityType::class, [
            'documentation' => [
                'type' => 'array',
                'items' => ['type' => 'integer', 'description' => 'Activity ID'],
                'description' => 'Array of activity IDs',
            ],
            'choices' => [],
            'multiple' => $multiActivity,
        ]);

        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) use ($name, $multiActivity, $multiProject) {
                $data = $event->getData();
                $event->getForm()->add($name, ActivityType::class, [
                    'multiple' => $multiActivity,
                    'required' => false,
                    'query_builder' => function (ActivityRepository $repo) use ($data, $multiActivity, $multiProject) {
                        $query = new ActivityFormTypeQuery();

                        $name = $multiActivity ? 'activities' : 'activity';
                        if (isset($data[$name]) && !empty($data[$name])) {
                            // we need to pre-fetch the activities to see if they are global, see ActivityFormTypeQuery::isGlobalsOnly()
                            $activities = $data[$name];
                            if (!\is_array($activities)) {
                                $activities = [$activities];
                            }
                            foreach ($activities as $activity) {
                                if ($activity instanceof Activity) {
                                    $query->addActivity($activity);
                                } elseif ($activity !== null) {
                                    $tmp = $repo->find($activity);
                                    if (null !== $tmp) {
                                        $query->addActivity($tmp);
                                    }
                                }
                            }
                        }

                        $name = $multiProject ? 'projects' : 'project';
                        if (isset($data[$name]) && !empty($data[$name])) {
                            if ($multiProject) {
                                $query->setProjects($data[$name]);
                            } else {
                                $query->addProject($data[$name]);
                            }
                        }

                        return $repo->getQueryBuilderForFormType($query);
                    },
                ]);
            }
        );
    }

    protected function addHiddenPagination(FormBuilderInterface $builder)
    {
        $builder->add('page', HiddenType::class, [
            'documentation' => [
                'type' => 'integer',
                'description' => 'Page number. Default: 1',
            ],
            'empty_data' => 1
        ]);
    }

    protected function addHiddenOrder(FormBuilderInterface $builder)
    {
        @trigger_error('addHiddenOrder() is deprecated and will be removed with 2.0, use the new search modal instead', E_USER_DEPRECATED);

        $builder->add('order', HiddenType::class, [
            'documentation' => [
                'type' => 'string',
                'description' => 'The order for returned items',
            ],
            'constraints' => [
                new Choice(['choices' => [BaseQuery::ORDER_ASC, BaseQuery::ORDER_DESC]])
            ]
        ]);
    }

    protected function addOrder(FormBuilderInterface $builder)
    {
        $builder->add('order', ChoiceType::class, [
            'documentation' => [
                'description' => 'The order for returned items',
            ],
            'label' => 'label.order',
            'choices' => [
                'label.asc' => BaseQuery::ORDER_ASC,
                'label.desc' => BaseQuery::ORDER_DESC
            ],
            'search' => false,
        ]);
    }

    protected function addHiddenOrderBy(FormBuilderInterface $builder, array $allowedColumns)
    {
        @trigger_error('addHiddenOrderBy() is deprecated and will be removed with 2.0, use the new search modal instead', E_USER_DEPRECATED);

        $builder->add('orderBy', HiddenType::class, [
            'constraints' => [
                new Choice(['choices' => $allowedColumns])
            ]
        ]);
    }

    protected function addOrderBy(FormBuilderInterface $builder, array $allowedColumns)
    {
        $all = [];
        foreach ($allowedColumns as $id => $name) {
            $label = \is_int($id) ? 'label.' . $name : $id;
            $all[$label] = $name;
        }
        $builder->add('orderBy', ChoiceType::class, [
            'label' => 'label.orderBy',
            'choices' => $all,
            'search' => false,
        ]);
    }

    protected function addTagInputField(FormBuilderInterface $builder)
    {
        $builder->add('tags', TagsType::class, [
            'required' => false
        ]);
    }

    protected function addSearchTermInputField(FormBuilderInterface $builder)
    {
        $builder->add('searchTerm', SearchTermType::class);
    }

    protected function addTimesheetStateChoice(FormBuilderInterface $builder)
    {
        $builder->add('state', ChoiceType::class, [
            'label' => 'label.entryState',
            'required' => false,
            'placeholder' => null,
            'search' => false,
            'choices' => [
                'entryState.all' => TimesheetQuery::STATE_ALL,
                'entryState.running' => TimesheetQuery::STATE_RUNNING,
                'entryState.stopped' => TimesheetQuery::STATE_STOPPED
            ],
        ]);
    }

    protected function addExportStateChoice(FormBuilderInterface $builder)
    {
        $builder->add('exported', ChoiceType::class, [
            'label' => 'label.exported',
            'required' => false,
            'placeholder' => null,
            'search' => false,
            'choices' => [
                'entryState.all' => TimesheetQuery::STATE_ALL,
                'entryState.exported' => TimesheetQuery::STATE_EXPORTED,
                'entryState.not_exported' => TimesheetQuery::STATE_NOT_EXPORTED
            ],
        ]);
    }

    protected function addBillableChoice(FormBuilderInterface $builder)
    {
        $builder->add('billable', BillableType::class, [
            'required' => false,
            'placeholder' => null,
            'search' => false,
        ]);
    }
}
