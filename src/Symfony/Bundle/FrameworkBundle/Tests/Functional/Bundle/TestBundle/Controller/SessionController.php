<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerAware;

class SessionController extends ContainerAware
{
    public function welcomeAction($name = null)
    {
        $request = $this->container->get('request');
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

    public function logoutAction()
    {
        $request = $this->container->get('request')->getSession('session')->invalidate();

        return new Response('Session cleared.');
    }

    public function setFlashAction($message)
    {
        $request = $this->container->get('request');
        $session = $request->getSession();
        $session->getFlashBag()->set('notice', $message);

        return new RedirectResponse($this->container->get('router')->generate('session_showflash'));
    }

    public function showFlashAction()
    {
        $request = $this->container->get('request');
        $session = $request->getSession();

        if ($session->getFlashBag()->has('notice')) {
            list($output) = $session->getFlashBag()->get('notice');
        } else {
            $output = 'No flash was set.';
        }

        return new Response($output);
    }
}
