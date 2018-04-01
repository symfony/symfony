<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\SecurityBundle\Tests\Functional\Bundle\CsrfFormLoginBundle\Form;

use Symphony\Component\Form\AbstractType;
use Symphony\Component\Form\FormBuilderInterface;
use Symphony\Component\Form\FormError;
use Symphony\Component\Form\FormEvents;
use Symphony\Component\Form\FormEvent;
use Symphony\Component\HttpFoundation\RequestStack;
use Symphony\Component\OptionsResolver\OptionsResolver;
use Symphony\Component\Security\Core\Security;

/**
 * Form type for use with the Security component's form-based authentication
 * listener.
 *
 * @author Henrik Bjornskov <henrik@bjrnskov.dk>
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class UserLoginType extends AbstractType
{
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('username', 'Symphony\Component\Form\Extension\Core\Type\TextType')
            ->add('password', 'Symphony\Component\Form\Extension\Core\Type\PasswordType')
            ->add('_target_path', 'Symphony\Component\Form\Extension\Core\Type\HiddenType')
        ;

        $request = $this->requestStack->getCurrentRequest();

        /* Note: since the Security component's form login listener intercepts
         * the POST request, this form will never really be bound to the
         * request; however, we can match the expected behavior by checking the
         * session for an authentication error and last username.
         */
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($request) {
            if ($request->attributes->has(Security::AUTHENTICATION_ERROR)) {
                $error = $request->attributes->get(Security::AUTHENTICATION_ERROR);
            } else {
                $error = $request->getSession()->get(Security::AUTHENTICATION_ERROR);
            }

            if ($error) {
                $event->getForm()->addError(new FormError($error->getMessage()));
            }

            $event->setData(array_replace((array) $event->getData(), array(
                'username' => $request->getSession()->get(Security::LAST_USERNAME),
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
            'csrf_token_id' => 'authenticate',
        ));
    }
}
