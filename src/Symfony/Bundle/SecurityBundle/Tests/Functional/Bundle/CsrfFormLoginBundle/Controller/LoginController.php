<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\CsrfFormLoginBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class LoginController implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function loginAction()
    {
        $form = $this->container->get('form.factory')->create('Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\CsrfFormLoginBundle\Form\UserLoginType');

        return new Response($this->container->get('twig')->render('@CsrfFormLogin/Login/login.html.twig', [
            'form' => $form->createView(),
        ]));
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
