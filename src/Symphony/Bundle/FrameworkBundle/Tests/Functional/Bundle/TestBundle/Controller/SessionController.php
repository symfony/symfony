<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller;

use Symphony\Component\DependencyInjection\ContainerAwareInterface;
use Symphony\Component\DependencyInjection\ContainerAwareTrait;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\HttpFoundation\RedirectResponse;

class SessionController implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function welcomeAction(Request $request, $name = null)
    {
        $session = $request->getSession();

        // new session case
        if (!$session->has('name')) {
            if (!$name) {
                return new Response('You are new here and gave no name.');
            }

            // remember name
            $session->set('name', $name);

            return new Response(sprintf('Hello %s, nice to meet you.', $name));
        }

        // existing session
        $name = $session->get('name');

        return new Response(sprintf('Welcome back %s, nice to meet you.', $name));
    }

    public function logoutAction(Request $request)
    {
        $request->getSession()->invalidate();

        return new Response('Session cleared.');
    }

    public function setFlashAction(Request $request, $message)
    {
        $session = $request->getSession();
        $session->getFlashBag()->set('notice', $message);

        return new RedirectResponse($this->container->get('router')->generate('session_showflash'));
    }

    public function showFlashAction(Request $request)
    {
        $session = $request->getSession();

        if ($session->getFlashBag()->has('notice')) {
            list($output) = $session->getFlashBag()->get('notice');
        } else {
            $output = 'No flash was set.';
        }

        return new Response($output);
    }
}
