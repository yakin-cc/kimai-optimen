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
            'placeholder' => 'Select a month',
            'label' => false, 
            'required' => false, 
        ])

        ->add('selectedUser', ChoiceType::class, [
            'choices' => [],
            'placeholder' => 'Select a user',
            'label' => false, 
            'required' => false, 
        ])
        ->add('activity', ChoiceType::class, [
            'choices' => [],
            'placeholder' => 'Select an activity',
            'label' => false,
            'required' => false,
        ])
        
        //Dinamically update the month field, to show only the active months of the project.
        ->addEventListener(FormEvents::PRE_SUBMIT, function (PreSubmitEvent $event): void {
            $data = $event->getData();
            $form = $event->getForm();
            $previousProjectId = $this->session->get('previousProjectId')?? null;       
            $selectedProjectId = $data['project'] ?? null;

            //This clears the month data, if only the project field has been updated.
            if ($previousProjectId != $selectedProjectId){
                $data['month'] = null;
                $data['selectedUser'] = null;
                $data['activities'] = null;
                $event->setData($data);
            }

            if ($selectedProjectId){
                $activeMonths = $this->service->findMonthsForProject($selectedProjectId);
                $activeUsers = $this->service->findUsersForProject($selectedProjectId);
                $activities = $this->service->findActivitiesForProject($selectedProjectId);

                $form->add('month', ChoiceType::class, [
                    'choices' => array_combine($activeMonths, $activeMonths),
                    'placeholder' => 'Filter by month',
                    'label' => false, 
                    'required' => false, 
                ])
    
                ->add('selectedUser', ChoiceType::class, [
                    'choices' => array_combine($activeUsers, $activeUsers),
                    'placeholder' => 'Filter by user',
                    'label' => false, 
                    'required' => false, 
                ])
                ->add('activity', ChoiceType::class, [
                    'choices' => array_combine($activities, $activities),
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
