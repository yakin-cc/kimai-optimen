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
use Symfony\Component\Form\FormEvents;
use App\Project\ProjectStatisticService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSubmitEvent;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use DateTime;

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
        $builder->add('project', ProjectType::class, [
            'ignore_date' => true,
            'required' => false,
            'label' => false,
            'width' => false,
            'join_customer' => true,
        ])

        ->add('month', ChoiceType::class, [
            'choices' => [],
            'placeholder' => 'Filter by month',
            'label' => false, 
            'required' => false, 
        ])

        ->add('selectedUser', ChoiceType::class, [
            'choices' => [],
            'placeholder' => 'Filter by user',
            'label' => false, 
            'required' => false, 
        ])
        ->add('activity', ChoiceType::class, [
            'choices' => [],
            'placeholder' => 'Filter ',
            'label' => false,
            'required' => false,
        ])
        
        
        //Dinamically update the month field, to show only the active months of the project.
        ->addEventListener(FormEvents::PRE_SUBMIT, function (PreSubmitEvent $event): void {
            $data = $event->getData();
            $form = $event->getForm();
            dump($this->session);
            // Set default values if keys are not present
            $previousProjectId = $this->session->get('previousProjectId') ?? null;       
            $selectedProjectId = $data['project'] ?? null;
            $data['month'] = $data['month'] ?? null;
            $data['selectedUser'] = $data['selectedUser'] ?? null;
            $data['activity'] = $data['activity'] ?? null;

            //This clears the month data, if only the project field has been updated.
            if ($previousProjectId != $selectedProjectId){
                $data['month'] = null;
                $data['selectedUser'] = null;
                $data['activities'] = null;
                $event->setData($data);
            }

            if ($selectedProjectId){
                $activeMonths = $this->service->findMonthsForProject($selectedProjectId);
                $data['month'] = ($data['month'] != null) ? new DateTime($data['month']) : null;
                
                $activeUsers = $this->service->findUsersForProject($selectedProjectId, $data['month']);
                $activities = $this->service->findActivitiesForProject($selectedProjectId, $data['month']);
        

                $form->add('month', ChoiceType::class, [
                    'choices' => $activeMonths,
                    'choice_label'=> function($month){
                        return $month instanceof DateTime ? $month->format('F Y') : '';
                    },
                    'choice_value'=> function($month){
                        return $month instanceof DateTime ? $month->format('Y-m-d H:i:s') : '';
                    },
                    'placeholder' => 'Filter by month',
                    'label' => false, 
                    'required' => false, 
                ])

                ->add('selectedUser', ChoiceType::class, [
                    'choices' => $activeUsers,
                    'choice_label' => function($user) {
                        return $user->getAlias(); // Method to display the user alias
                    },
                    'choice_value' => function($user) {
                        return $user ? $user->getId() : ''; // Method to get the user ID
                    },
                    'placeholder' => 'Filter by user',
                    'label' => false, 
                    'required' => false, 
                ])
                ->add('activity', ChoiceType::class, [
                    'choices' => $activities,
                    'choice_label' => function($activity) {
                        return $activity->getName(); // Method to display the activity name
                    },
                    'choice_value' => function($activity) {
                        return $activity ? $activity->getId() : ''; // Method to get the activity ID
                    },
                    'placeholder' => 'Filter by activity',
                    'label' => false,
                    'required' => false,
                ]);
    
                $this->session->set('previousProjectId', $selectedProjectId);
            }
        });
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
