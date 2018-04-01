<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\SecurityBundle\Tests\Functional\Bundle\FormLoginBundle\Controller;

use Symphony\Component\DependencyInjection\ContainerAwareInterface;
use Symphony\Component\DependencyInjection\ContainerAwareTrait;
use Symphony\Component\Security\Core\Security;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\Response;

class LocalizedController implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function loginAction(Request $request)
    {
        // get the login error if there is one
        if ($request->attributes->has(Security::AUTHENTICATION_ERROR)) {
            $error = $request->attributes->get(Security::AUTHENTICATION_ERROR);
        } else {
            $error = $request->getSession()->get(Security::AUTHENTICATION_ERROR);
        }

        return new Response($this->container->get('twig')->render('@FormLogin/Localized/login.html.twig', array(
            // last username entered by the user
            'last_username' => $request->getSession()->get(Security::LAST_USERNAME),
            'error' => $error,
        )));
    }

    public function loginCheckAction()
    {
        throw new \RuntimeException('loginCheckAction() should never be called.');
    }

    public function logoutAction()
    {
        throw new \RuntimeException('logoutAction() should never be called.');
    }

    public function secureAction()
    {
        throw new \RuntimeException('secureAction() should never be called.');
    }

    public function profileAction()
    {
        return new Response('Profile');
    }

    public function homepageAction()
    {
        return new Response('Homepage');
    }
}
