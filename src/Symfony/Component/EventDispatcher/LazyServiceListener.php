<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A listener forwarding its invocation to a service.
 */
class LazyServiceListener
{
    /**
     * The container from where service is loaded
     * @var ContainerInterface
     */
    private $container;

    /**
     * The service id.
     * @var string
     */
    private $serviceId;

    /**
     * The name of a method on the service.
     * @var string
     */
    private $method;

    /**
     * Constructor.
     *
     * @param ContainerInterface  $container  The service container
     * @param string              $serviceId  The service identifier
     * @param string              $method     The method name
     */
    public function __construct(ContainerInterface $container, $serviceId, $method) {
      $this->container = $container;
      $this->serviceId = $serviceId;
      $this->method = $method;
    }

    /**
     * Retrieves the service from the container and forwards the method call.
     */
    public function __invoke(Event $event, $eventName, EventDispatcherInterface $dispatcher) {
        $service = $this->container->get($this->serviceId);
        $service->{$this->method}($event, $eventName, $dispatcher);
    }

    /**
     * Returns the container.
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Returns the service id.
     */
    public function getServiceId()
    {
        return $this->serviceId;
    }

    /**
     * Returns the method name.
     */
    public function getMethod()
    {
        return $this->method;
    }
}
