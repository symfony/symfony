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
 * A base class for listeners that can tell whether they should authenticate incoming requests.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
abstract class AbstractListener
{
    final public function __invoke(RequestEvent $event)
    {
        if (false !== $this->supports($event->getRequest())) {
            $this->authenticate($event);
        }
    }

    /**
     * Tells whether the authenticate() method should be called or not depending on the incoming request.
     *
     * Returning null means authenticate() can be called lazily when accessing the token storage.
     */
    abstract public function supports(Request $request): ?bool;

    /**
     * Does whatever is required to authenticate the request, typically calling $event->setResponse() internally.
     */
    abstract public function authenticate(RequestEvent $event);
}
