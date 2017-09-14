<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * A controller resolver searching for a controller in a psr-11 container when using the "service:method" notation.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class ContainerControllerResolver extends ControllerResolver
{
    protected $container;

    public function __construct(ContainerInterface $container, LoggerInterface $logger = null)
    {
        $this->container = $container;

        parent::__construct($logger);
    }

    /**
     * Returns a callable for the given controller.
     *
     * @param string $controller A Controller string
     *
     * @return mixed A PHP callable
     *
     * @throws \LogicException           When the name could not be parsed
     * @throws \InvalidArgumentException When the controller class does not exist
     */
    protected function createController($controller)
    {
        if (false !== strpos($controller, '::')) {
            return parent::createController($controller);
        }

        if (1 == substr_count($controller, ':')) {
            // controller in the "service:method" notation
            list($service, $method) = explode(':', $controller, 2);

            return array($this->container->get($service), $method);
        }

        if ($this->container->has($controller) && method_exists($service = $this->container->get($controller), '__invoke')) {
            // invokable controller in the "service" notation
            return $service;
        }

        throw new \LogicException(sprintf('Unable to parse the controller name "%s".', $controller));
    }

    /**
     * {@inheritdoc}
     */
    protected function instantiateController($class)
    {
        if ($this->container->has($class)) {
            return $this->container->get($class);
        }

        return parent::instantiateController($class);
    }
}
