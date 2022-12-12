<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\FormLoginBundle\Controller;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Twig\Environment;

class LocalizedController implements ServiceSubscriberInterface
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function loginAction(Request $request)
    {
        // get the login error if there is one
        if ($request->attributes->has(SecurityRequestAttributes::AUTHENTICATION_ERROR)) {
            $error = $request->attributes->get(SecurityRequestAttributes::AUTHENTICATION_ERROR);
        } else {
            $error = $request->getSession()->get(SecurityRequestAttributes::AUTHENTICATION_ERROR);
        }

        return new Response($this->container->get('twig')->render('@FormLogin/Localized/login.html.twig', [
            // last username entered by the user
            'last_username' => $request->getSession()->get(SecurityRequestAttributes::LAST_USERNAME),
            'error' => $error,
        ]));
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
        return new Response('<html><body>Profile</body></html>');
    }

    public function homepageAction()
    {
        return (new Response('<html><body>Homepage</body></html>'))->setPublic();
    }

    public static function getSubscribedServices(): array
    {
        return [
            'twig' => Environment::class,
        ];
    }
}
