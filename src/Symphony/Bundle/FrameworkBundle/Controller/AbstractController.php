<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Controller;

use Psr\Container\ContainerInterface;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symphony\Component\DependencyInjection\ServiceSubscriberInterface;
use Symphony\Component\Form\FormFactoryInterface;
use Symphony\Component\HttpFoundation\RequestStack;
use Symphony\Component\HttpFoundation\Session\SessionInterface;
use Symphony\Component\HttpKernel\HttpKernelInterface;
use Symphony\Component\Messenger\MessageBusInterface;
use Symphony\Component\Routing\RouterInterface;
use Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symphony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symphony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symphony\Component\Serializer\SerializerInterface;
use Symphony\Component\Templating\EngineInterface;
use Twig\Environment;

/**
 * Provides common features needed in controllers.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
abstract class AbstractController implements ServiceSubscriberInterface
{
    use ControllerTrait;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @internal
     * @required
     */
    public function setContainer(ContainerInterface $container)
    {
        $previous = $this->container;
        $this->container = $container;

        return $previous;
    }

    /**
     * Gets a container parameter by its name.
     *
     * @return mixed
     *
     * @final
     */
    protected function getParameter(string $name)
    {
        if (!$this->container->has('parameter_bag')) {
            throw new \LogicException('The "parameter_bag" service is not available. Try running "composer require dependency-injection:^4.1"');
        }

        return $this->container->get('parameter_bag')->get($name);
    }

    public static function getSubscribedServices()
    {
        return array(
            'router' => '?'.RouterInterface::class,
            'request_stack' => '?'.RequestStack::class,
            'http_kernel' => '?'.HttpKernelInterface::class,
            'serializer' => '?'.SerializerInterface::class,
            'session' => '?'.SessionInterface::class,
            'security.authorization_checker' => '?'.AuthorizationCheckerInterface::class,
            'templating' => '?'.EngineInterface::class,
            'twig' => '?'.Environment::class,
            'doctrine' => '?'.ManagerRegistry::class,
            'form.factory' => '?'.FormFactoryInterface::class,
            'security.token_storage' => '?'.TokenStorageInterface::class,
            'security.csrf.token_manager' => '?'.CsrfTokenManagerInterface::class,
            'parameter_bag' => '?'.ContainerInterface::class,
            'message_bus' => '?'.MessageBusInterface::class,
        );
    }
}
