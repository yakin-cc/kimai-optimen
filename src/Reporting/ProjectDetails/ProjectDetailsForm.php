<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Reporting\ProjectDetails;

use App\Form\Type\ProjectType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormEvents;
use App\Project\ProjectStatisticService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSubmitEvent;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use DateTime;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;

class ProjectDetailsForm extends AbstractType
{
    /*
     * StatisticService to get months dinamically.
     */
    private $service;

    /*
     * User session, to store the previous project Id and handle some logic with it. 
     */
    private $session;
    public function __construct(ProjectStatisticService $service, SessionInterface $session)
    {
        $this->service = $service;
        $this->session = $session;
    }

    /**
     * Simplify cross linking between pages by removing the block prefix.
     *
     * @return null|string
     */
    public function getBlockPrefix()
    {
        return null;
    }
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $formBuilder = $this->addProjectField($builder);
        $formBuilder = $this->addMonthField($formBuilder);
        $formBuilder = $this->addUserField($formBuilder);
        $formBuilder = $this->addActivityField($formBuilder);
        $formBuilder = $this->addResetButton($formBuilder);

        $formBuilder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'onPreSubmit']);
    }

    private function addProjectField(FormBuilderInterface $builder)
    {
        $builder->add('project', ProjectType::class, [
            'ignore_date' => true,
            'required' => false,
            'label' => false,
            'width' => false,
            'join_customer' => true,
        ]);
    
        return $builder;
    }
    
    private function addMonthField(FormBuilderInterface $builder)
    {
        $builder->add('month', ChoiceType::class, [
            'choices' => [],
            'placeholder' => 'Filter by month',
            'label' => false,
            'required' => false,
        ]);
    
        return $builder;
    }
    
    private function addUserField(FormBuilderInterface $builder)
    {
        $builder->add('selectedUser', ChoiceType::class, [
            'choices' => [],
            'placeholder' => 'Filter by user',
            'label' => false,
            'required' => false,
        ]);
    
        return $builder;
    }
    
    private function addActivityField(FormBuilderInterface $builder)
    {
        $builder->add('activity', ChoiceType::class, [
            'choices' => [],
            'placeholder' => 'Filter by activity',
            'label' => false,
            'required' => false,
        ]);
    
        return $builder;
    }
    
    private function addResetButton(FormBuilderInterface $builder)
    {
        $builder->add('reset', ButtonType::class, [
            'label' => 'Reset Filters',
            'attr' => ['class' => 'btn btn-secondary', 'onclick' => 'resetFilters()'],
        ]);
    
        return $builder;
    }
    
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

    private function getDefaultData(array $data)
    {
        return array_merge([
            'project' => null,
            'month' => null,
            'selectedUser' => null,
            'activity' => null,
        ], $data);
    }

    private function isProjectChanged(array $data)
    {
        $previousProjectId = $this->session->get('previousProjectId');
        $currentProjectId = $data['project'] ?? null;
        return $previousProjectId !== $currentProjectId;
    }

    private function resetData(array $data)
    {
        $data['month'] = null;
        $data['selectedUser'] = null;
        $data['activity'] = null;
        return $data;
    }

    private function updateFormFields(FormInterface $form, $projectId, array $data)
    {
        $months = $this->service->findMonthsForProject($projectId);
        $data['month'] = ($data['month'] != null) ? new DateTime($data['month']) : null;
        $users = $this->service->findUsersForProject($projectId, $data['month']);
        $activities = $this->service->findActivitiesForProject($projectId, $data['month']);

        $form->add('month', ChoiceType::class, [
            'choices' => $months,
            'choice_label' => function ($month) {
                return $month instanceof DateTime ? $month->format('F Y') : '';
            },
            'choice_value' => function ($month) {
                return $month instanceof DateTime ? $month->format('Y-m-d H:i:s') : '';
            },
            'placeholder' => 'Filter by month',
            'label' => false,
            'required' => false,
        ])
        ->add('selectedUser', ChoiceType::class, [
            'choices' => $users,
            'choice_label' => function ($user) {
                return $user->getAlias();
            },
            'choice_value' => function ($user) {
                return $user ? $user->getId() : '';
            },
            'placeholder' => 'Filter by user',
            'label' => false,
            'required' => false,
        ])
        ->add('activity', ChoiceType::class, [
            'choices' => $activities,
            'choice_label' => function ($activity) {
                return $activity->getName();
            },
            'choice_value' => function ($activity) {
                return $activity ? $activity->getId() : '';
            },
            'placeholder' => 'Filter by activity',
            'label' => false,
            'required' => false,
        ]);
    }
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ProjectDetailsQuery::class,
            'csrf_protection' => false,
            'method' => 'GET',
            'validation_groups' => false,
        ]);
    }
}