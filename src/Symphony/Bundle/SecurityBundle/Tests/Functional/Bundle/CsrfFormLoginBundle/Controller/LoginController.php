<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\SecurityBundle\Tests\Functional\Bundle\CsrfFormLoginBundle\Controller;

use Symphony\Component\DependencyInjection\ContainerAwareInterface;
use Symphony\Component\DependencyInjection\ContainerAwareTrait;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\Security\Core\Exception\AccessDeniedException;

class LoginController implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function loginAction()
    {
        $form = $this->container->get('form.factory')->create('Symphony\Bundle\SecurityBundle\Tests\Functional\Bundle\CsrfFormLoginBundle\Form\UserLoginType');

        return new Response($this->container->get('twig')->render('@CsrfFormLogin/Login/login.html.twig', array(
            'form' => $form->createView(),
        )));
    }

    public function afterLoginAction()
    {
        return new Response($this->container->get('twig')->render('@CsrfFormLogin/Login/after_login.html.twig'));
    }

    public function loginCheckAction()
    {
        return new Response('', 400);
    }

    public function secureAction()
    {
        throw new \Exception('Wrapper', 0, new \Exception('Another Wrapper', 0, new AccessDeniedException()));
    }
}
