<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form;

use App\Configuration\SystemConfiguration;
use App\Entity\User;
use App\Form\Type\AvatarType;
use App\Form\Type\LanguageType;
use App\Form\Type\TimezoneType;
use App\Form\Type\YesNoType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Defines the form used to edit the profile of a User.
 */
class UserEditType extends AbstractType
{
    use ColorTrait;

    private $configuration;

    public function __construct(SystemConfiguration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('alias', TextType::class, [
                'label' => 'label.alias',
                'required' => false,
            ])
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'required' => false,
            ])
            ->add('accountNumber', TextType::class, [
                'label' => 'label.account_number',
                'required' => false,
            ])
        ;

        if ($this->configuration->isThemeAllowAvatarUrls()) {
            $builder->add('avatar', AvatarType::class, [
                'required' => false,
            ]);
        }

        $this->addColor($builder);

        $builder
            ->add('email', EmailType::class, [
                'label' => 'label.email',
            ])
        ;

        if ($options['include_preferences']) {
            $builder->add('language', LanguageType::class, [
                'required' => true,
            ]);

            $builder->add('timezone', TimezoneType::class, [
                'required' => true,
            ]);
        }

        if ($options['include_active_flag']) {
            $builder
                ->add('enabled', YesNoType::class, [
                    'label' => 'label.active',
                ])
            ;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'validation_groups' => ['Profile'],
            'data_class' => User::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'edit_user_profile',
            'include_active_flag' => true,
            'include_preferences' => true,
        ]);
    }
}
