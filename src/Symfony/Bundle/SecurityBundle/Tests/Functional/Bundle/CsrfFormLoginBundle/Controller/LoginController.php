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

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class LoginController extends ContainerAware
{
    public function loginAction()
    {
        $form = $this->container->get('form.factory')->create('user_login');

        return $this->container->get('templating')->renderResponse('CsrfFormLoginBundle:Login:login.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    public function afterLoginAction()
    {
        return $this->container->get('templating')->renderResponse('CsrfFormLoginBundle:Login:after_login.html.twig');
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
