<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\FirewallMapInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This is a lazy-loading firewall map implementation.
 *
 * Listeners will only be initialized if we really need them.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class FirewallMap implements FirewallMapInterface, EventSubscriberInterface
{
    protected $container;
    protected $map;
    private $contexts;

    public function __construct(ContainerInterface $container, array $map)
    {
        $this->container = $container;
        $this->map = $map;
        $this->contexts = new \SplObjectStorage();
    }

    /**
     * {@inheritdoc}
     */
    public function getListeners(Request $request)
    {
        $context = $this->getFirewallContext($request);

        if (null === $context) {
            return array(array(), null);
        }

        return $context->getContext();
    }

    /**
     * @return FirewallConfig|null
     */
    public function getFirewallConfig(Request $request)
    {
        $context = $this->getFirewallContext($request);

        if (null === $context) {
            return;
        }

        return $context->getConfig();
    }

    private function getFirewallContext(Request $request)
    {
        if ($this->contexts->contains($request)) {
            return $this->contexts[$request];
        }

        foreach ($this->map as $contextId => $requestMatcher) {
            if (null === $requestMatcher || $requestMatcher->matches($request)) {
                return $this->contexts[$request] = $this->container->get($contextId);
            }
        }
    }

    /**
     * @param FinishRequestEvent $event
     */
    public function onKernelFinishRequest(FinishRequestEvent $event)
    {
        $this->detachListeners($event->getRequest());
    }

    /**
     * Cleans up the internal state of the firewall map.
     *
     * @param Request $request
     */
    private function detachListeners(Request $request)
    {
        unset($this->contexts[$request]);
    }

    /**
     * Get subscribed events.
     *
     * @return array Subscribed events
     */
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::FINISH_REQUEST => 'onKernelFinishRequest',
        );
    }
}
