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

use Psr\Container\ContainerInterface;
use Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\CsrfFormLoginBundle\Form\UserLoginType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Twig\Environment;

class LoginController implements ServiceSubscriberInterface
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function loginAction()
    {
        $form = $this->container->get('form.factory')->create(UserLoginType::class);

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

    public static function getSubscribedServices(): array
    {
        return [
            'form.factory' => FormFactoryInterface::class,
            'twig' => Environment::class,
        ];
    }
}
