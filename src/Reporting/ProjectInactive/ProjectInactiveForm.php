<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Reporting\ProjectInactive;

use App\Form\Type\DateTimePickerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjectInactiveForm extends AbstractType
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
        $builder->add('lastChange', DateTimePickerType::class, [
            'label' => 'label.last_record_before',
            'model_timezone' => $options['timezone'],
            'view_timezone' => $options['timezone'],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ProjectInactiveQuery::class,
            'timezone' => date_default_timezone_get(),
            'csrf_protection' => false,
            'method' => 'GET',
        ]);
    }
}
