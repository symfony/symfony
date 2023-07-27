<?php

use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @internal This class has been auto-generated by the Symfony Dependency Injection Component.
 */
class Symfony_DI_PhpDumper_Issues49591 extends Container
{
    protected $parameters = [];
    protected \Closure $getService;

    public function __construct()
    {
        $this->services = $this->privates = [];
        $this->methodMap = [
            'connection' => 'getConnectionService',
            'session' => 'getSessionService',
            'subscriber' => 'getSubscriberService',
        ];

        $this->aliases = [];
    }

    public function compile(): void
    {
        throw new LogicException('You cannot compile a dumped container that was already compiled.');
    }

    public function isCompiled(): bool
    {
        return true;
    }

    public function getRemovedIds(): array
    {
        return [
            '.service_locator.SoPO3vR' => true,
            'repository' => true,
        ];
    }

    /**
     * Gets the public 'connection' shared service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Connection
     */
    protected static function getConnectionService($container)
    {
        return $container->services['connection'] = new \Symfony\Component\DependencyInjection\Tests\Connection(new \Symfony\Component\DependencyInjection\Argument\ServiceLocator($container->getService ??= $container->getService(...), [
            'subscriber' => ['services', 'subscriber', 'getSubscriberService', false],
        ], [
            'subscriber' => 'Symfony\\Component\\DependencyInjection\\Tests\\Subscriber',
        ]));
    }

    /**
     * Gets the public 'session' shared service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Session
     */
    protected static function getSessionService($container)
    {
        $a = ($container->services['connection'] ?? self::getConnectionService($container));

        if (isset($container->services['session'])) {
            return $container->services['session'];
        }

        return $container->services['session'] = (new \Symfony\Component\DependencyInjection\Tests\Repository())->login($a);
    }

    /**
     * Gets the public 'subscriber' shared service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Subscriber
     */
    protected static function getSubscriberService($container)
    {
        $a = ($container->services['session'] ?? self::getSessionService($container));

        if (isset($container->services['subscriber'])) {
            return $container->services['subscriber'];
        }

        return $container->services['subscriber'] = new \Symfony\Component\DependencyInjection\Tests\Subscriber($a);
    }
}
