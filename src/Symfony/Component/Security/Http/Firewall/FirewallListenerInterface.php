<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Firewall;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Can be implemented by firewall listeners.
 *
 * @author Christian Scheb <me@christianscheb.de>
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
interface FirewallListenerInterface
{
    /**
     * Tells whether the authenticate() method should be called or not depending on the incoming request.
     *
     * Returning null means authenticate() can be called lazily when accessing the token storage.
     */
    public function supports(Request $request): ?bool;

    /**
     * Does whatever is required to authenticate the request, typically calling $event->setResponse() internally.
     *
     * @return void
     */
    public function authenticate(RequestEvent $event);

    /**
     * Defines the priority of the listener.
     * The higher the number, the earlier a listener is executed.
     */
    public static function getPriority(): int;
}
