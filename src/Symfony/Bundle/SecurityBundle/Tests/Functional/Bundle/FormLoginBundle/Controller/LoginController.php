<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\FormLoginBundle\Controller;

use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\SecurityContext;
use Symfony\Component\DependencyInjection\ContainerAware;

class LoginController extends ContainerAware
{
    public function loginAction()
    {
        // get the login error if there is one
        if ($this->container->get('request')->attributes->has(SecurityContext::AUTHENTICATION_ERROR)) {
            $error = $this->container->get('request')->attributes->get(SecurityContext::AUTHENTICATION_ERROR);
        } else {
            $error = $this->container->get('request')->getSession()->get(SecurityContext::AUTHENTICATION_ERROR);
        }

        return $this->container->get('templating')->renderResponse('FormLoginBundle:Login:login.html.twig', array(
            // last username entered by the user
            'last_username' => $this->container->get('request')->getSession()->get(SecurityContext::LAST_USERNAME),
            'error'         => $error,
        ));
    }

    public function afterLoginAction()
    {
        return $this->container->get('templating')->renderResponse('FormLoginBundle:Login:after_login.html.twig');
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
