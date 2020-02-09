<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\SubmitEvent;
use Symfony\Component\Form\Exception\InvalidConfigurationException;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @author Loïck Piera <pyrech@gmail.com>
 */
class SecurityPasswordType extends AbstractType
{
    private $passwordEncoder;

    public function __construct(UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->passwordEncoder = $passwordEncoder;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::SUBMIT, function (SubmitEvent $event) {
            $securityUser = $event->getForm()->getConfig()->getOption('security_user');

            if (!$securityUser) {
                $parentData = $event->getForm()->getParent()->getData();

                if (!$parentData instanceof UserInterface) {
                    throw new InvalidConfigurationException(sprintf('You should either use "%s" inside a parent form where data is an instance of "%s" or specify the user in "security_user" option', self::class, UserInterface::class));
                }

                $securityUser = $parentData;
            }

            $plainPassword = $event->getData();

            $event->setData($plainPassword ? $this->passwordEncoder->encodePassword($securityUser, $plainPassword) : $securityUser->getPassword());
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['required'] = false;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('security_user', null);
        $resolver->setAllowedTypes('security_user', [
            'null',
            UserInterface::class,
        ]);
    }

    public function getParent()
    {
        return PasswordType::class;
    }
}
