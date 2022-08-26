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
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Twig\Environment;

class LoginController implements ServiceSubscriberInterface
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function loginAction(Request $request, UserInterface $user = null)
    {
        // get the login error if there is one
        if ($request->attributes->has(SecurityRequestAttributes::AUTHENTICATION_ERROR)) {
            $error = $request->attributes->get(SecurityRequestAttributes::AUTHENTICATION_ERROR);
        } else {
            $error = $request->getSession()->get(SecurityRequestAttributes::AUTHENTICATION_ERROR);
        }

        return new Response($this->container->get('twig')->render('@FormLogin/Login/login.html.twig', [
            // last username entered by the user
            'last_username' => $request->getSession()->get(SecurityRequestAttributes::LAST_USERNAME),
            'error' => $error,
        ]));
    }

    public function afterLoginAction(UserInterface $user)
    {
        return new Response($this->container->get('twig')->render('@FormLogin/Login/after_login.html.twig', ['user' => $user]));
    }

    public function loginCheckAction()
    {
        return new Response('', 400);
    }

    public function secureAction()
    {
        throw new \Exception('Wrapper', 0, new \Exception('Another Wrapper', 0, new AccessDeniedException()));
    }

    public static function getSubscribedServices(): array
    {
        return [
            'twig' => Environment::class,
        ];
    }
}
