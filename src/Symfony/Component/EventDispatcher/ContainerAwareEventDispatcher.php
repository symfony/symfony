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
 * Lazily loads listeners from the dependency injection container.
 *
 * @author Jáchym Toušek <enumag@gmail.com>
 */
class ContainerAwareEventDispatcher extends LazyEventDispatcher
{
    /**
     * The container from where services are loaded.
     *
     * @var ContainerInterface
     */
    private $container;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container A ContainerInterface instance
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct(function ($serviceId) use ($container) {
            return $container->get($serviceId);
        });
    }

    public function getContainer()
    {
        return $this->container;
    }
}
