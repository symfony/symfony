<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Form type for use with the Security component's form-based authentication
 * listener.
 *
 * @author Henrik Bjornskov <henrik@bjrnskov.dk>
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class UsernamePasswordType extends AbstractType
{
    private $authenticationUtils;

    public function __construct(AuthenticationUtils $authenticationUtils)
    {
        $this->authenticationUtils = $authenticationUtils;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('_username', 'Symfony\Component\Form\Extension\Core\Type\TextType')
            ->add('_password', 'Symfony\Component\Form\Extension\Core\Type\PasswordType')
            ->add('_target_path', 'Symfony\Component\Form\Extension\Core\Type\HiddenType')
        ;

        /* Note: since the Security component's form login listener intercepts
         * the POST request, this form will never really be bound to the
         * request; however, we can match the expected behavior by checking the
         * session for an authentication error and last username.
         */
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            if (null !== $error = $this->authenticationUtils->getLastAuthenticationError()) {
                $event->getForm()->addError(new FormError($error->getMessage()));
            }

            $event->setData(array_replace((array) $event->getData(), array(
                '_username' => $this->authenticationUtils->getLastUsername(),
            )));
        });
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        /* Note: the form's csrf_token_id must correspond to that for the form login
         * listener in order for the CSRF token to validate successfully.
         */

        $resolver->setDefaults(array(
            'csrf_field_name' => '_csrf_token',
            'csrf_token_id' => 'authenticate',
        ));
    }
}
