<?php

namespace App\Reporting\ProjectDetails;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActivityDetailsForm extends AbstractType
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
            ->add('activity', ChoiceType::class, [
                'choices' => array_combine($options['activities'], $options['activities']),
                'placeholder' => 'Select an activity',
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
            'activities' => [],
            'data_class' => ActivityDetailsQuery::class,
            'csrf_protection' => false,
            'method' => 'GET',
            'validation_groups' => false,
        ]);
    }
}
