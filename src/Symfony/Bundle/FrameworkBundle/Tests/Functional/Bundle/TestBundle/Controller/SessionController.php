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

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SessionController implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function welcomeAction(Request $request, $name = null)
    {
        $session = $request->getSession();

        // new session case
        if (!$session->has('name')) {
            if (!$name) {
                return new Response('<html><body>You are new here and gave no name.</body></html>');
            }

            // remember name
            $session->set('name', $name);

            return new Response(sprintf('<html><body>Hello %s, nice to meet you.</body></html>', $name));
        }

        // existing session
        $name = $session->get('name');

        return new Response(sprintf('<html><body>Welcome back %s, nice to meet you.</body></html>', $name));
    }

    public function cacheableAction()
    {
        $response = new Response('all good');
        $response->setSharedMaxAge(100);

        return $response;
    }

    public function logoutAction(Request $request)
    {
        $request->getSession()->invalidate();

        return new Response('<html><body>Session cleared.</body></html>');
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
            [$output] = $session->getFlashBag()->get('notice');
        } else {
            $output = 'No flash was set.';
        }

        return new Response('<html><body>'.$output.'</body></html>');
    }
}
