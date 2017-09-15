<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Clean up services between requests.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 */
class ServiceResetListener implements EventSubscriberInterface
{
    private $services;
    private $resetMethods;

    public function __construct(\Traversable $services, array $resetMethods)
    {
        $this->services = $services;
        $this->resetMethods = $resetMethods;
    }

    public function onKernelTerminate()
    {
        foreach ($this->services as $id => $service) {
            $method = $this->resetMethods[$id];
            $service->$method();
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::TERMINATE => array('onKernelTerminate', -2048),
        );
    }
}
