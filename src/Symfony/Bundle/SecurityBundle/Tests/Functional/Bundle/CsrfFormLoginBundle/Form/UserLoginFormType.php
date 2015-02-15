<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\CsrfFormLoginBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
<<<<<<< HEAD
use Symfony\Component\HttpFoundation\Request;
=======
use Symfony\Component\HttpFoundation\RequestStack;
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;

/**
 * Form type for use with the Security component's form-based authentication
 * listener.
 *
 * @author Henrik Bjornskov <henrik@bjrnskov.dk>
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class UserLoginFormType extends AbstractType
{
<<<<<<< HEAD
    private $request;

    /**
     * @param Request $request A request instance
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
=======
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('username', 'text')
            ->add('password', 'password')
            ->add('_target_path', 'hidden')
        ;

<<<<<<< HEAD
        $request = $this->request;
=======
        $request = $this->requestStack->getCurrentRequest();
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d

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
        /* Note: the form's intention must correspond to that for the form login
         * listener in order for the CSRF token to validate successfully.
         */

        $resolver->setDefaults(array(
            'intention' => 'authenticate',
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'user_login';
    }
}
