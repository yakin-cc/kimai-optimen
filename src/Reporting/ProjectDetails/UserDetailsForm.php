<?php

namespace App\Reporting\ProjectDetails;

use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSubmitEvent;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class UserDetailsForm extends AbstractType
{
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
        $builder
            ->add('user', ChoiceType::class, [
                'choices' => array_combine($options['activeUsers'], $options['activeUsers']),
                'placeholder' => 'Select a user',
                'label' => false, 
                'required' => false, 
            ])
            ->add('activity', ChoiceType::class, [
                'choices' => array_combine($options['activities'], $options['activities']),
                'placeholder' => 'Select an activity',
                'label' => false,
                'required' => false,
            ])

            ->addEventListener(FormEvents::PRE_SUBMIT, function (PreSubmitEvent $event): void {
                $data = $event->getData();
                $form = $event->getForm();

                $selectedUser = $data['user'];
                $selectedActivity = $data['activity'];

                echo "<script>console.log('Selected User: ". $selectedUser ."');</script>";
                echo "<script>console.log('Selected Activity: ". $selectedActivity ."');</script>";
            });
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'activeUsers' => [],
            'activities' => [],
            'data_class' => UserDetailsQuery::class,
            'csrf_protection' => false,
            'method' => 'POST',
            'validation_groups' => false,
        ]);
    }
}
