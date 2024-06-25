<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form;

use App\Entity\Customer;
use App\Entity\Project;
use App\Form\Type\CustomerType;
use App\Form\Type\DateTimePickerType;
use App\Form\Type\YesNoType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjectEditForm extends AbstractType
{
    use EntityFormTrait;

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $customer = null;
        $id = null;
        $options['currency'] = null;

        if (isset($options['data'])) {
            /** @var Project $entry */
            $entry = $options['data'];
            $id = $entry->getId();

            if (null !== $entry->getCustomer()) {
                $customer = $entry->getCustomer();
                $options['currency'] = $customer->getCurrency();
            }
        }

        $dateTimeOptions = [
            'model_timezone' => $options['timezone'],
            'view_timezone' => $options['timezone'],
        ];
        // primarily for API usage, where we cannot use a user/locale specific format
        if (null !== $options['date_format']) {
            $dateTimeOptions['format'] = $options['date_format'];
        }

        $timeIncrement = 1;
        if ($options['time_increment'] >= 1 && $options['time_increment'] <= 60) {
            $timeIncrement = $options['time_increment'];
        }

        $builder
            ->add('name', TextType::class, [
                'label' => 'label.name',
                'attr' => [
                    'autofocus' => 'autofocus'
                ],
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'label.description',
                'required' => false,
            ])
            ->add('invoiceText', TextareaType::class, [
                'label' => 'label.invoiceText',
                'required' => false,
            ])
            ->add('orderNumber', TextType::class, [
                'label' => 'label.orderNumber',
                'required' => false,
            ])
            ->add('orderDate', DateTimePickerType::class, array_merge($dateTimeOptions, [
                'label' => 'label.orderDate',
                'required' => false,
                'time_increment' => $timeIncrement,
            ]))
            ->add('start', DateTimePickerType::class, array_merge($dateTimeOptions, [
                'label' => 'label.project_start',
                'required' => false,
                'time_increment' => $timeIncrement,
            ]))
            ->add('end', DateTimePickerType::class, array_merge($dateTimeOptions, [
                'label' => 'label.project_end',
                'required' => false,
                'time_increment' => $timeIncrement,
            ]))
            ->add('customer', CustomerType::class, [
                'placeholder' => (null === $id && null === $customer) ? '' : false,
                'customers' => $customer,
                'query_builder_for_user' => true,
            ])
            ->add('globalActivities', YesNoType::class, [
                'label' => 'label.globalActivities',
            ])
        ;

        $this->addCommonFields($builder, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'admin_project_edit',
            'currency' => Customer::DEFAULT_CURRENCY,
            'date_format' => null,
            'include_budget' => false,
            'include_time' => false,
            'timezone' => date_default_timezone_get(),
            'time_increment' => 1,
            'attr' => [
                'data-form-event' => 'kimai.projectUpdate'
            ],
        ]);
    }
}
