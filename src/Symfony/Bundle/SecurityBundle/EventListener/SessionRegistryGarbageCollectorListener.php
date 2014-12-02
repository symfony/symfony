<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Session\SessionRegistry;

/**
 * Clear session information from registry for idle sessions
 *
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 */
class SessionRegistryGarbageCollectorListener implements EventSubscriberInterface
{
    /**
     * @var SessionRegistry
     */
    private $sessionRegistry;
    private $maxLifetime;
    private $probability;
    private $divisor;

    public function __construct(SessionRegistry $sessionRegistry, $maxLifetime = 1, $probability = null, $divisor = null)
    {
        $this->sessionRegistry = $sessionRegistry;
        $this->maxLifetime = $maxLifetime ?: ini_get('session.gc_maxlifetime');
        $this->probability = $probability ?: ini_get('session.gc_probability');
        $this->divisor = $divisor ?: ini_get('session.gc_divisor');
    }

    public function onKernelTerminate(PostResponseEvent $event)
    {
        if ($this->probability / $this->divisor > lcg_value() || true) {
            $this->sessionRegistry->collectGarbage($this->maxLifetime);
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::TERMINATE => array(array('onKernelTerminate')),
        );
    }
}
