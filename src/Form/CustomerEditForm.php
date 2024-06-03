<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form;

use App\Entity\Customer;
use App\Form\Type\MailType;
use App\Form\Type\TimezoneType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomerEditForm extends AbstractType
{
    use EntityFormTrait;

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (isset($options['data'])) {
            /** @var Customer $customer */
            $customer = $options['data'];
            $options['currency'] = $customer->getCurrency();
        }

        $builder
            ->add('name', TextType::class, [
                'label' => 'label.name',
                'attr' => [
                    'autofocus' => 'autofocus'
                ],
            ])
            ->add('number', TextType::class, [
                'label' => 'label.number',
                'required' => false,
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'label.description',
                'required' => false,
            ])
            ->add('company', TextType::class, [
                'label' => 'label.company',
                'required' => false,
            ])
            ->add('vatId', TextType::class, [
                'label' => 'label.vat_id',
                'required' => false,
            ])
            ->add('contact', TextType::class, [
                'label' => 'label.contact',
                'required' => false,
            ])
            ->add('address', TextareaType::class, [
                'label' => 'label.address',
                'required' => false,
            ])
            ->add('country', CountryType::class, [
                'label' => 'label.country',
            ])
            ->add('currency', CurrencyType::class, [
                'label' => 'label.currency',
            ])
            ->add('phone', TelType::class, [
                'label' => 'label.phone',
                'required' => false,
            ])
            ->add('fax', TelType::class, [
                'label' => 'label.fax',
                'required' => false,
                'attr' => ['icon' => 'fax'],
            ])
            ->add('mobile', TelType::class, [
                'label' => 'label.mobile',
                'required' => false,
                'attr' => ['icon' => 'mobile'],
            ])
            ->add('email', MailType::class, [
                'required' => false,
            ])
            ->add('homepage', UrlType::class, [
                'label' => 'label.homepage',
                'required' => false,
            ])
            ->add('timezone', TimezoneType::class, [
                'label' => 'label.timezone',
            ]);

        $this->addCommonFields($builder, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Customer::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'admin_customer_edit',
            'currency' => Customer::DEFAULT_CURRENCY,
            'include_budget' => false,
            'include_time' => false,
            'attr' => [
                'data-form-event' => 'kimai.customerUpdate'
            ],
        ]);
    }
}
