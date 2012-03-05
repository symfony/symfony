<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\CsrfFormLoginBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\SecurityContextInterface;

/**
 * Form type for use with the Security component's form-based authentication
 * listener.
 *
 * @author Henrik Bjornskov <henrik@bjrnskov.dk>
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class UserLoginFormType extends AbstractType
{
    private $reqeust;

    /**
     * @param Request $request A request instance
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @see Symfony\Component\Form\AbstractType::buildForm()
     */
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder
            ->add('username', 'text')
            ->add('password', 'password')
            ->add('_target_path', 'hidden')
        ;

        $request = $this->request;

        /* Note: since the Security component's form login listener intercepts
         * the POST request, this form will never really be bound to the
         * request; however, we can match the expected behavior by checking the
         * session for an authentication error and last username.
         */
        $builder->addEventListener(FormEvents::SET_DATA, function (FilterDataEvent $event) use ($request) {
            if ($request->attributes->has(SecurityContextInterface::AUTHENTICATION_ERROR)) {
                $error = $request->attributes->get(SecurityContextInterface::AUTHENTICATION_ERROR);
            } else {
                $error = $request->getSession()->get(SecurityContextInterface::AUTHENTICATION_ERROR);
            }

            if ($error) {
                $event->getForm()->addError(new FormError($error->getMessage()));
            }

            $event->setData(array_replace((array) $event->getData(), array(
                'username' => $request->getSession()->get(SecurityContextInterface::LAST_USERNAME),
            )));
        });
    }

    /**
     * @see Symfony\Component\Form\AbstractType::getDefaultOptions()
     */
    public function getDefaultOptions(array $options)
    {
        /* Note: the form's intention must correspond to that for the form login
         * listener in order for the CSRF token to validate successfully.
         */
        return array(
            'intention' => 'authenticate',
        );
    }

    /**
     * @see Symfony\Component\Form\FormTypeInterface::getName()
     */
    public function getName()
    {
        return 'user_login';
    }
}
